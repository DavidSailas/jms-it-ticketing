<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JMS One IT's own people (Super Admin, Admin, IT Engineer) never belong to a partner company.
 * Only Customers belong to a partner company, and a Customer only ever sees their own tickets.
 * Only an Admin or Super Admin assigns tickets; an IT Engineer only works on what was assigned to them.
 */
class RoleFlowTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private Company $globex;
    private User $super;
    private User $admin;
    private User $marco;
    private User $ana;
    private User $anna;    // customer of Acme
    private User $ben;     // another customer of Acme (same company as Anna)
    private User $gina;    // customer of Globex

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme   = Company::create(['name' => 'Acme Corp']);
        $this->globex = Company::create(['name' => 'Globex Ltd']);

        $this->super = $this->person('super_admin', null, 'Boss Super');
        $this->admin = $this->person('admin', null, 'Grace Admin');
        $this->marco = $this->person('it_support', null, 'Marco Engineer');
        $this->ana   = $this->person('it_support', null, 'Ana Engineer');
        $this->anna  = $this->person('user', $this->acme, 'Anna Customer');
        $this->ben   = $this->person('user', $this->acme, 'Ben Customer');
        $this->gina  = $this->person('user', $this->globex, 'Gina Customer');
    }

    private function person(string $role, ?Company $company, string $name): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'name' => $name, 'role' => $role, 'company_id' => $company?->id,
            'username' => 'flow' . $n, 'email' => "flow{$n}@example.test",
        ]);
    }

    private function ticket(User $by, string $subject, array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'user_id' => $by->id, 'subject' => $subject, 'description' => 'Something is not working properly.',
            'category' => 'Hardware', 'priority' => 'high', 'status' => 'open',
        ], $extra));
    }

    private function assign(User $actor, Ticket $ticket, User $engineer)
    {
        return $this->actingAs($actor)->post(route('tickets.assign', $ticket), [
            'assigned_to' => $engineer->id, 'support_type' => 'remote', 'priority' => 'high',
        ]);
    }

    private function fresh(Ticket $t): Ticket
    {
        return Ticket::withoutGlobalScopes()->findOrFail($t->id);
    }

    // ---- Roles and companies ------------------------------------------------------------

    public function test_role_labels_use_customer_and_it_engineer(): void
    {
        $this->assertSame('Customer', $this->anna->roleLabel());
        $this->assertSame('IT Engineer', $this->marco->roleLabel());
        $this->assertSame('Admin', $this->admin->roleLabel());
        $this->assertSame('Super Admin', $this->super->roleLabel());
    }

    public function test_our_own_people_always_belong_to_jms_and_never_to_a_partner_company(): void
    {
        // Even if a partner company is supplied, staff accounts are saved without it.
        $staff = User::factory()->create(['role' => 'it_support', 'company_id' => $this->acme->id]);

        $this->assertNull($staff->fresh()->company_id);
        $this->assertSame('JMS One IT', $staff->fresh()->company);

        $this->assertSame($this->acme->id, $this->anna->fresh()->company_id);
        $this->assertSame('Acme Corp', $this->anna->fresh()->company);
    }

    public function test_moving_a_customer_to_an_engineer_drops_the_partner_company(): void
    {
        $this->anna->update(['role' => 'it_support']);

        $this->assertNull($this->anna->fresh()->company_id);
        $this->assertSame('JMS One IT', $this->anna->fresh()->company);
    }

    // ---- Who sees which ticket ----------------------------------------------------------

    public function test_a_customer_only_sees_their_own_tickets_not_a_colleagues(): void
    {
        $mine   = $this->ticket($this->anna, 'Anna printer jam');
        $theirs = $this->ticket($this->ben, 'Ben keyboard broken');   // same company

        $this->actingAs($this->anna)->get(route('tickets.index'))->assertOk()
            ->assertSee('Anna printer jam')->assertDontSee('Ben keyboard broken');

        $this->actingAs($this->anna)->get(route('tickets.show', $mine))->assertOk();
        $this->actingAs($this->anna)->get(route('tickets.show', $theirs))->assertNotFound();
    }

    public function test_an_engineer_only_sees_tickets_assigned_to_them(): void
    {
        $assigned   = $this->ticket($this->anna, 'Assigned to Marco', ['assigned_to' => $this->marco->id, 'status' => 'assigned']);
        $other      = $this->ticket($this->ben, 'Assigned to Ana', ['assigned_to' => $this->ana->id, 'status' => 'assigned']);
        $unassigned = $this->ticket($this->gina, 'Waiting for the admin');

        $this->actingAs($this->marco)->get(route('tickets.index'))->assertOk()
            ->assertSee('Assigned to Marco')
            ->assertDontSee('Assigned to Ana')
            ->assertDontSee('Waiting for the admin');

        $this->actingAs($this->marco)->get(route('tickets.show', $assigned))->assertOk();
        $this->actingAs($this->marco)->get(route('tickets.show', $other))->assertNotFound();
        $this->actingAs($this->marco)->get(route('tickets.show', $unassigned))->assertNotFound();
    }

    public function test_admin_and_super_admin_see_every_ticket(): void
    {
        $a = $this->ticket($this->anna, 'Acme ticket');
        $g = $this->ticket($this->gina, 'Globex ticket');

        foreach ([$this->admin, $this->super] as $who) {
            $this->actingAs($who)->get(route('tickets.show', $a))->assertOk();
            $this->actingAs($who)->get(route('tickets.show', $g))->assertOk();
        }
    }

    // ---- Who can assign -----------------------------------------------------------------

    public function test_an_admin_can_assign_an_engineer(): void
    {
        $ticket = $this->ticket($this->anna, 'Needs an engineer');

        $this->assign($this->admin, $ticket, $this->marco)->assertSessionHasNoErrors();

        $this->assertSame($this->marco->id, (int) $this->fresh($ticket)->assigned_to);
        $this->assertSame('assigned', $this->fresh($ticket)->status);
    }

    public function test_a_super_admin_can_assign_and_reassign_an_engineer(): void
    {
        $ticket = $this->ticket($this->gina, 'Needs an engineer');

        $this->assign($this->super, $ticket, $this->marco)->assertSessionHasNoErrors();
        $this->assign($this->super, $ticket, $this->ana)->assertSessionHasNoErrors();

        $this->assertSame($this->ana->id, (int) $this->fresh($ticket)->assigned_to);
    }

    public function test_an_engineer_cannot_assign_or_pick_a_ticket(): void
    {
        $ticket = $this->ticket($this->anna, 'Open ticket');

        // Blocked either way: the engineer cannot even see a ticket nobody assigned to them.
        $this->assertContains($this->assign($this->marco, $ticket, $this->marco)->status(), [403, 404]);
        $this->assertNull($this->fresh($ticket)->assigned_to);

        // Same when it is already theirs: assigning is an Admin / Super Admin action only.
        $mine = $this->ticket($this->anna, 'Marco job', ['assigned_to' => $this->marco->id, 'status' => 'assigned']);
        $this->assign($this->marco, $mine, $this->ana)->assertForbidden();
        $this->assertSame($this->marco->id, (int) $this->fresh($mine)->assigned_to);
    }

    public function test_a_customer_cannot_assign_a_ticket(): void
    {
        $ticket = $this->ticket($this->anna, 'My own ticket');

        $this->assign($this->anna, $ticket, $this->marco)->assertForbidden();
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    public function test_only_it_engineers_can_be_assigned_never_admins_or_customers(): void
    {
        $ticket = $this->ticket($this->anna, 'Needs an engineer');

        $this->assign($this->admin, $ticket, $this->admin)->assertSessionHasErrors('assigned_to');
        $this->assign($this->admin, $ticket, $this->ben)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    public function test_the_assign_list_offers_only_our_it_engineers(): void
    {
        $ticket = $this->ticket($this->anna, 'Needs an engineer');

        $this->actingAs($this->admin)->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('Marco Engineer')->assertSee('Ana Engineer')
            ->assertDontSee('Company IT team')->assertDontSee('Need JMS support');
    }

    // ---- Dashboards ---------------------------------------------------------------------

    public function test_an_engineer_with_nothing_assigned_sees_a_friendly_empty_dashboard(): void
    {
        $this->ticket($this->anna, 'Not assigned to anyone');

        $this->actingAs($this->marco)->get(route('dashboard'))->assertOk()
            ->assertSee('Nothing is assigned to you right now')
            ->assertSee('You are all caught up')
            ->assertDontSee('Not assigned to anyone');
    }

    public function test_an_engineer_dashboard_shows_only_their_assigned_ticket(): void
    {
        $this->ticket($this->anna, 'Marco job', ['assigned_to' => $this->marco->id, 'status' => 'assigned']);
        $this->ticket($this->ben, 'Ana job', ['assigned_to' => $this->ana->id, 'status' => 'assigned']);

        $this->actingAs($this->marco)->get(route('dashboard'))->assertOk()
            ->assertSee('Marco job')->assertDontSee('Ana job');
    }

    // ---- Managing accounts --------------------------------------------------------------

    public function test_a_customer_must_be_placed_in_a_partner_company(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'No Company', 'username' => 'nocompany', 'email' => 'nocompany@example.test', 'role' => 'user',
        ])->assertSessionHasErrors('company_id');

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'With Company', 'username' => 'withcompany', 'email' => 'withcompany@example.test',
            'role' => 'user', 'company_id' => $this->globex->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->globex->id, (int) User::where('email', 'withcompany@example.test')->value('company_id'));
    }

    public function test_an_admin_can_create_an_engineer_who_belongs_to_jms_even_if_a_company_is_sent(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'New Engineer', 'username' => 'newengineer', 'email' => 'newengineer@example.test',
            'role' => 'it_support', 'company_id' => $this->acme->id,
        ])->assertSessionHasNoErrors();

        $u = User::where('email', 'newengineer@example.test')->firstOrFail();
        $this->assertNull($u->company_id);
        $this->assertSame('JMS One IT', $u->company);
    }

    public function test_only_a_super_admin_can_create_an_admin(): void
    {
        $payload = ['name' => 'New Admin', 'username' => 'newadmin', 'email' => 'newadmin@example.test', 'role' => 'admin'];

        $this->actingAs($this->admin)->post(route('users.store'), $payload)->assertSessionHasErrors('role');
        $this->assertNull(User::where('email', 'newadmin@example.test')->first());

        $this->actingAs($this->super)->post(route('users.store'), $payload)->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'newadmin@example.test')->value('company_id'));
    }

    public function test_the_users_page_lists_customers_and_engineers_for_an_admin(): void
    {
        $this->actingAs($this->admin)->get(route('users.index'))->assertOk()
            ->assertSee('Anna Customer')->assertSee('Marco Engineer')
            ->assertDontSee('Boss Super');
    }

    public function test_customers_and_engineers_cannot_open_user_management(): void
    {
        $this->actingAs($this->anna)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->marco)->get(route('users.index'))->assertForbidden();
    }

    public function test_the_partner_branding_page_is_gone(): void
    {
        $this->actingAs($this->admin)->get('/branding')->assertNotFound();
    }

    public function test_the_jms_company_page_lists_the_whole_team_and_no_customers(): void
    {
        $jms = Company::create(['name' => 'JMS One IT']);

        $this->actingAs($this->super)->get(route('companies.show', $jms))->assertOk()
            ->assertSee('Our company')->assertSee('Grace Admin')->assertSee('Marco Engineer')->assertSee('Ana Engineer')
            ->assertDontSee('Anna Customer');
    }

    public function test_a_partner_company_page_lists_only_its_customers(): void
    {
        $this->actingAs($this->super)->get(route('companies.show', $this->acme))->assertOk()
            ->assertSee('Anna Customer')->assertSee('Ben Customer')
            ->assertDontSee('Marco Engineer')->assertDontSee('Gina Customer');
    }

    public function test_the_companies_page_shows_only_customers_and_open_tickets_for_a_partner(): void
    {
        $this->ticket($this->anna, 'Open one');

        $res = $this->actingAs($this->super)->get(route('companies.index'))->assertOk();
        $res->assertSee('Acme Corp')->assertSee('Customers')->assertSee('Open ticket')
            ->assertDontSee('IT Support')->assertDontSee('No admin yet')->assertDontSee('its admin');

        $acme = $res->viewData('companies')->firstWhere('id', $this->acme->id);
        $this->assertSame(2, (int) $acme->users_count);   // Anna and Ben
        $this->assertSame(1, (int) $acme->open_tickets_count);
    }

    public function test_creating_a_company_can_add_its_first_customer_never_an_admin(): void
    {
        $this->actingAs($this->super)->post(route('companies.store'), [
            'name' => 'Initech', 'customer_name' => 'Peter Gibbons', 'customer_email' => 'peter@initech.test', 'customer_username' => 'peter',
        ])->assertSessionHasNoErrors();

        $company = Company::where('name', 'Initech')->firstOrFail();
        $peter   = User::where('email', 'peter@initech.test')->firstOrFail();
        $this->assertSame('user', $peter->role);
        $this->assertSame($company->id, (int) $peter->company_id);
    }

    public function test_staff_without_a_company_are_not_listed_as_waiting_to_be_placed(): void
    {
        $res = $this->actingAs($this->super)->get(route('companies.index'))->assertOk();

        $this->assertSame(0, (int) $res->viewData('unassigned'));   // Marco, Ana and Grace belong to JMS on purpose
    }
}
