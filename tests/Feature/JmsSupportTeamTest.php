<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1: a partner company reports a problem, JMS (super admin) assigns one of JMS's own engineers.
 */
class JmsSupportTeamTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private Company $globex;
    private User $super;
    private User $acmeAdmin;
    private User $acmeUser;
    private User $globexUser;
    private User $jmsEngineer;
    private User $acmeEngineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme   = Company::create(['name' => 'Acme Corp']);
        $this->globex = Company::create(['name' => 'Globex Ltd']);

        $this->super        = $this->person('super_admin', null, 'Boss Super');
        $this->acmeAdmin    = $this->person('admin', $this->acme, 'Acme Admin');
        $this->acmeUser     = $this->person('user', $this->acme, 'Acme Staff');
        $this->globexUser   = $this->person('user', $this->globex, 'Globex Staff');
        $this->jmsEngineer  = $this->person('it_support', null, 'Jun JMS Engineer');
        $this->acmeEngineer = $this->person('it_support', $this->acme, 'Acme Own Engineer');
    }

    private function person(string $role, ?Company $company, string $name): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'name' => $name, 'role' => $role, 'company_id' => $company?->id,
            'username' => 'person' . $n, 'email' => "person{$n}@example.test",
        ]);
    }

    private function ticket(User $by, string $subject, array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'user_id' => $by->id, 'subject' => $subject, 'description' => 'Something is not working properly.',
            'category' => 'Database', 'priority' => 'high', 'status' => 'open',
        ], $extra));
    }

    private function assignTo(User $actor, Ticket $ticket, User $engineer)
    {
        return $this->actingAs($actor)->post(route('tickets.assign', $ticket), [
            'assigned_to' => $engineer->id, 'support_type' => 'remote', 'priority' => 'high',
        ]);
    }

    private function fresh(Ticket $t): Ticket
    {
        return Ticket::withoutGlobalScopes()->findOrFail($t->id);
    }

    // ---- Roles ------------------------------------------------------------------

    public function test_it_support_without_a_company_is_a_jms_engineer(): void
    {
        $this->assertTrue($this->jmsEngineer->isJmsEngineer());
        $this->assertSame('JMS One IT', $this->jmsEngineer->company);

        $this->assertFalse($this->acmeEngineer->isJmsEngineer());
        $this->assertSame('Acme Corp', $this->acmeEngineer->company);
        $this->assertFalse($this->super->isJmsEngineer());
    }

    // ---- Assignment ---------------------------------------------------------------

    public function test_super_admin_can_assign_a_jms_engineer_to_a_partner_ticket(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Production database crashed');

        $this->assignTo($this->super, $ticket, $this->jmsEngineer)->assertSessionHasNoErrors();

        $ticket = $this->fresh($ticket);
        $this->assertSame($this->jmsEngineer->id, (int) $ticket->assigned_to);
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($this->acme->id, (int) $ticket->company_id);
    }

    public function test_super_admin_is_offered_only_jms_engineers_never_a_partners_it_team(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'CCTV offline', ['category' => 'CCTV / Surveillance']);

        $this->actingAs($this->super)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('JMS support team')
            ->assertSee('Jun JMS Engineer')
            ->assertDontSee('Company IT team')
            ->assertDontSee('Acme Own Engineer');

        // Even by a hand-built request, JMS cannot assign a partner company's own IT person.
        $this->assignTo($this->super, $ticket, $this->acmeEngineer)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    public function test_jms_engineers_are_not_offered_for_another_partners_ticket_only_by_company_rules(): void
    {
        // A different partner's own engineer must never be offered on Acme's ticket.
        $globexEngineer = $this->person('it_support', $this->globex, 'Globex Own Engineer');
        $ticket = $this->ticket($this->acmeUser, 'Network down');

        $this->actingAs($this->super)->get(route('tickets.show', $ticket))
            ->assertOk()->assertSee('Jun JMS Engineer')->assertDontSee('Globex Own Engineer');

        $this->assignTo($this->super, $ticket, $globexEngineer)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    public function test_partner_admin_cannot_see_or_assign_jms_engineers(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Server will not boot');

        // Only JMS (super admins and JMS admins) dispatch JMS engineers. A partner admin only gets their own IT team.
        $this->actingAs($this->acmeAdmin)->get(route('tickets.show', $ticket))
            ->assertOk()->assertDontSee('Jun JMS Engineer')->assertDontSee('JMS support team')->assertSee('Acme Own Engineer');

        $this->assignTo($this->acmeAdmin, $ticket, $this->jmsEngineer)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    public function test_partner_admin_can_still_assign_their_own_engineer(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Printer jam');

        $this->assignTo($this->acmeAdmin, $ticket, $this->acmeEngineer)->assertSessionHasNoErrors();

        $this->assertSame($this->acmeEngineer->id, (int) $this->fresh($ticket)->assigned_to);
    }

    public function test_partner_admin_cannot_take_a_ticket_back_from_a_jms_engineer(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Database slow');
        $this->assignTo($this->super, $ticket, $this->jmsEngineer);

        $this->assignTo($this->acmeAdmin, $ticket, $this->acmeEngineer)->assertRedirect()->assertSessionHas('error');
        $this->assertSame($this->jmsEngineer->id, (int) $this->fresh($ticket)->assigned_to);
    }

    public function test_partner_admin_cannot_assign_another_partners_engineer(): void
    {
        $globexEngineer = $this->person('it_support', $this->globex, 'Globex Own Engineer');
        $ticket = $this->ticket($this->acmeUser, 'Network down');

        $this->assignTo($this->acmeAdmin, $ticket, $globexEngineer)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    // ---- What a JMS engineer can see ---------------------------------------------------

    public function test_jms_engineer_sees_only_tickets_assigned_to_them_across_partners(): void
    {
        $mine1  = $this->ticket($this->acmeUser, 'Acme database crashed');
        $mine2  = $this->ticket($this->globexUser, 'Globex CCTV offline', ['category' => 'CCTV / Surveillance']);
        $other  = $this->ticket($this->acmeUser, 'Acme unassigned problem');
        $this->assignTo($this->super, $mine1, $this->jmsEngineer);
        $this->assignTo($this->super, $mine2, $this->jmsEngineer);

        $this->actingAs($this->jmsEngineer)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Acme database crashed')
            ->assertSee('Globex CCTV offline')
            ->assertSee('Globex Ltd')
            ->assertDontSee('Acme unassigned problem');

        $this->actingAs($this->jmsEngineer)->get(route('tickets.show', $mine2))->assertOk();
        $this->actingAs($this->jmsEngineer)->get(route('tickets.show', $other))->assertNotFound();

        $this->actingAs($this->jmsEngineer)->get(route('dashboard'))
            ->assertOk()->assertSee('Acme database crashed')->assertSee('Globex CCTV offline');
    }

    public function test_jms_engineer_can_work_the_ticket_and_the_partner_is_told(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Database crashed');
        $this->assignTo($this->super, $ticket, $this->jmsEngineer);

        $this->actingAs($this->jmsEngineer)->post(route('tickets.progress', $ticket), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->jmsEngineer)->post(route('tickets.progress', $ticket), [
            'status' => 'resolved', 'resolution' => 'Restored the database from last night backup.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('resolved', $this->fresh($ticket)->status);
        $this->assertTrue($this->acmeUser->fresh()->notifications()->where('data->title', 'Your ticket was resolved')->exists());
        $this->assertTrue($this->jmsEngineer->fresh()->notifications()->where('data->title', 'Ticket assigned to you')->exists());
    }

    public function test_a_partner_user_never_sees_jms_internal_staff_lists(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Slow network');
        $this->assignTo($this->super, $ticket, $this->jmsEngineer);

        $this->actingAs($this->acmeUser)->get(route('tickets.show', $ticket))
            ->assertOk()->assertDontSee('JMS support team')->assertDontSee('Handled by JMS support');
    }

    // ---- New categories -------------------------------------------------------------------

    public function test_partners_can_report_database_cctv_network_and_server_problems(): void
    {
        foreach (['Database', 'CCTV / Surveillance', 'Network / Internet', 'Server / Infrastructure'] as $i => $category) {
            $this->actingAs($this->acmeAdmin)->post(route('tickets.store'), [
                'subject' => "Problem number {$i}", 'category' => $category, 'priority' => 'critical',
                'description' => 'Everything stopped working this morning.', 'when' => 'now',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(4, Ticket::withoutGlobalScopes()->where('company_id', $this->acme->id)->count());
    }

    public function test_unknown_category_is_rejected_and_form_offers_the_new_ones(): void
    {
        $this->actingAs($this->acmeAdmin)->post(route('tickets.store'), [
            'subject' => 'Unknown thing', 'category' => 'Coffee machine', 'priority' => 'low',
            'description' => 'It makes a strange sound.', 'when' => 'now',
        ])->assertSessionHasErrors('category');

        $this->actingAs($this->acmeAdmin)->get(route('tickets.create'))
            ->assertOk()->assertSee('CCTV / Surveillance')->assertSee('Database')->assertSee('Server / Infrastructure');
    }

    // ---- Managing the JMS team ------------------------------------------------------------

    public function test_super_admin_can_create_a_jms_engineer_without_a_company(): void
    {
        $this->actingAs($this->super)->post(route('users.store'), [
            'name' => 'New JMS Engineer', 'username' => 'new.jms', 'email' => 'new.jms@example.test',
            'role' => 'it_support', 'company_id' => '',
        ])->assertSessionHasNoErrors();

        $u = User::where('email', 'new.jms@example.test')->firstOrFail();
        $this->assertNull($u->company_id);
        $this->assertSame('JMS One IT', $u->company);
        $this->assertTrue($u->isJmsEngineer());
    }

    public function test_a_company_is_still_required_for_users_and_admins(): void
    {
        foreach (['user', 'admin'] as $role) {
            $this->actingAs($this->super)->post(route('users.store'), [
                'name' => 'No Company', 'username' => "no.company.{$role}", 'email' => "no.company.{$role}@example.test",
                'role' => $role, 'company_id' => '',
            ])->assertSessionHasErrors('company_id');
        }
    }

    public function test_partner_admin_creating_it_support_keeps_them_in_their_own_company(): void
    {
        $this->actingAs($this->acmeAdmin)->post(route('users.store'), [
            'name' => 'Acme Tech', 'username' => 'acme.tech', 'email' => 'acme.tech@example.test', 'role' => 'it_support',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->acme->id, (int) User::where('email', 'acme.tech@example.test')->value('company_id'));
    }

    public function test_engineer_cannot_be_moved_to_a_company_while_holding_another_companys_tickets(): void
    {
        $ticket = $this->ticket($this->globexUser, 'Globex database down');
        $this->assignTo($this->super, $ticket, $this->jmsEngineer);

        $payload = fn (array $over = []) => array_merge([
            'name' => $this->jmsEngineer->name, 'username' => $this->jmsEngineer->username, 'email' => $this->jmsEngineer->email,
            'role' => 'it_support', 'company_id' => (string) $this->acme->id,
        ], $over);

        $this->actingAs($this->super)->patch(route('users.update', $this->jmsEngineer), $payload())
            ->assertSessionHasErrors('company_id', null, 'edit');
        $this->assertNull($this->jmsEngineer->fresh()->company_id);

        // Moving into the same company as the ticket is fine.
        $this->actingAs($this->super)->patch(route('users.update', $this->jmsEngineer), $payload(['company_id' => (string) $this->globex->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->globex->id, (int) $this->jmsEngineer->fresh()->company_id);
    }

    public function test_jms_engineers_are_not_flagged_as_unplaced_on_the_companies_page(): void
    {
        $this->actingAs($this->super)->get(route('companies.index'))->assertOk();
        $this->assertSame(0, User::whereIn('role', ['user', 'admin'])->whereNull('company_id')->count());
    }
}
