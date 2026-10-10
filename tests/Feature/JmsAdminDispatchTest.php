<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JMS's own admin (not only the super admin) can accept a partner's ticket and assign JMS engineers.
 */
class JmsAdminDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private User $super;
    private User $jmsAdmin;
    private User $acmeAdmin;
    private User $acmeUser;
    private User $jmsEngineer;
    private User $acmeEngineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme         = Company::create(['name' => 'Acme Corp']);
        $this->super        = $this->person('super_admin', null, 'Boss Super');
        $this->jmsAdmin     = $this->person('admin', null, 'Jun JMS Admin');
        $this->acmeAdmin    = $this->person('admin', $this->acme, 'Acme Admin');
        $this->acmeUser     = $this->person('user', $this->acme, 'Acme Staff');
        $this->jmsEngineer  = $this->person('it_support', null, 'Jun JMS Engineer');
        $this->acmeEngineer = $this->person('it_support', $this->acme, 'Acme Own Engineer');
    }

    private function person(string $role, ?Company $company, string $name): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'name' => $name, 'role' => $role, 'company_id' => $company?->id,
            'username' => 'jmsadmintest' . $n, 'email' => "jmsadmintest{$n}@example.test",
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

    public function test_a_jms_admin_is_recognised_and_labelled(): void
    {
        $this->assertTrue($this->jmsAdmin->isJmsAdmin());
        $this->assertTrue($this->jmsAdmin->canDispatchJms());
        $this->assertSame('JMS One IT', $this->jmsAdmin->company);
        $this->assertSame('JMS Admin', $this->jmsAdmin->roleLabel());

        $this->assertFalse($this->acmeAdmin->isJmsAdmin());
        $this->assertFalse($this->acmeAdmin->canDispatchJms());
        $this->assertSame('Admin', $this->acmeAdmin->roleLabel());
    }

    public function test_an_admin_attached_to_the_jms_company_record_is_also_a_jms_admin(): void
    {
        $jmsCompany = Company::create(['name' => 'JMSONEIT']);
        $admin = $this->person('admin', $jmsCompany, 'Record Admin');

        $this->assertTrue($admin->isJmsAdmin());

        $ticket = $this->ticket($this->acmeUser, 'Production database crashed');
        $this->assignTo($admin, $ticket, $this->jmsEngineer)->assertSessionHasNoErrors();
        $this->assertSame($this->jmsEngineer->id, (int) $this->fresh($ticket)->assigned_to);
    }

    public function test_an_old_admin_with_no_company_at_all_is_not_treated_as_jms(): void
    {
        $orphan = $this->person('admin', null, 'Unplaced Admin');
        $orphan->forceFill(['company' => null])->saveQuietly();

        $this->assertFalse($orphan->fresh()->isJmsAdmin());
        $this->assertFalse($orphan->fresh()->canDispatchJms());
    }

    public function test_jms_admin_can_assign_a_jms_engineer_to_a_partner_ticket(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Production database crashed');

        $this->assignTo($this->jmsAdmin, $ticket, $this->jmsEngineer)->assertSessionHasNoErrors();

        $ticket = $this->fresh($ticket);
        $this->assertSame($this->jmsEngineer->id, (int) $ticket->assigned_to);
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($this->acme->id, (int) $ticket->company_id);
    }

    public function test_jms_admin_sees_every_partner_ticket_but_is_offered_only_jms_engineers(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'CCTV offline', ['category' => 'CCTV / Surveillance']);

        $this->actingAs($this->jmsAdmin)->get(route('tickets.index'))->assertOk()->assertSee('CCTV offline');
        $this->actingAs($this->jmsAdmin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('JMS support team')->assertSee('Jun JMS Engineer')
            ->assertDontSee('Company IT team')->assertDontSee('Acme Own Engineer');

        $this->assignTo($this->jmsAdmin, $ticket, $this->acmeEngineer)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);
    }

    public function test_jms_admin_can_reassign_a_ticket_jms_is_already_handling(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Database slow');
        $this->assignTo($this->super, $ticket, $this->jmsEngineer);
        $other = $this->person('it_support', null, 'Second JMS Engineer');

        $this->assignTo($this->jmsAdmin, $ticket, $other)->assertSessionHasNoErrors();

        $this->assertSame($other->id, (int) $this->fresh($ticket)->assigned_to);
    }

    public function test_partner_admin_cannot_assign_jms_engineers_only_jms_can(): void
    {
        $ticket = $this->ticket($this->acmeUser, 'Server will not boot');

        $this->assignTo($this->acmeAdmin, $ticket, $this->jmsEngineer)->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($ticket)->assigned_to);

        // Their own IT team is still theirs to assign.
        $this->assignTo($this->acmeAdmin, $ticket, $this->acmeEngineer)->assertSessionHasNoErrors();
        $this->assertSame($this->acmeEngineer->id, (int) $this->fresh($ticket)->assigned_to);
    }

    public function test_jms_admin_is_alerted_about_new_tickets(): void
    {
        $this->actingAs($this->acmeUser)->post(route('tickets.store'), [
            'subject' => 'Network is down', 'category' => 'Network / Internet', 'priority' => 'critical',
            'description' => 'Nobody can reach the internet since morning.', 'when' => 'now',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->jmsAdmin->fresh()->notifications()->exists());
        $this->assertTrue($this->acmeAdmin->fresh()->notifications()->exists());
    }

    public function test_jms_admin_does_not_log_tickets_or_manage_companies_and_reports(): void
    {
        $this->actingAs($this->jmsAdmin)->get(route('tickets.create'))->assertForbidden();
        $this->actingAs($this->jmsAdmin)->get(route('companies.index'))->assertForbidden();
        $this->actingAs($this->jmsAdmin)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->jmsAdmin)->get(route('dashboard'))->assertOk()->assertSee('JMS Admin');
    }

    public function test_jms_admin_manages_only_jms_engineers_in_users(): void
    {
        $this->actingAs($this->jmsAdmin)->get(route('users.index'))
            ->assertOk()->assertSee('Jun JMS Engineer')
            ->assertDontSee('Acme Own Engineer')->assertDontSee('Acme Staff')->assertDontSee('Boss Super');

        $this->actingAs($this->jmsAdmin)->get(route('users.show', $this->acmeEngineer))->assertNotFound();

        $this->actingAs($this->jmsAdmin)->post(route('users.store'), [
            'name' => 'New JMS Engineer', 'username' => 'new.jms.eng', 'email' => 'new.jms.eng@example.test', 'role' => 'it_support',
        ])->assertSessionHasNoErrors();

        $created = User::where('email', 'new.jms.eng@example.test')->firstOrFail();
        $this->assertNull($created->company_id);
        $this->assertTrue($created->isJmsEngineer());

        // A JMS admin cannot create partner users or admins.
        foreach (['user', 'admin'] as $role) {
            $this->actingAs($this->jmsAdmin)->post(route('users.store'), [
                'name' => 'Nope', 'username' => "nope.{$role}", 'email' => "nope.{$role}@example.test", 'role' => $role,
            ])->assertSessionHasErrors('role');
        }
    }

    public function test_super_admin_can_create_a_jms_admin_from_the_jms_team_choice(): void
    {
        $this->actingAs($this->super)->post(route('users.store'), [
            'name' => 'Maria JMS Admin', 'username' => 'maria.jms', 'email' => 'maria.jms@example.test',
            'role' => 'admin', 'company_id' => 'jms',
        ])->assertSessionHasNoErrors();

        $u = User::where('email', 'maria.jms@example.test')->firstOrFail();
        $this->assertNull($u->company_id);
        $this->assertSame('JMS One IT', $u->company);
        $this->assertTrue($u->isJmsAdmin());
    }

    public function test_partner_users_cannot_join_the_jms_team_and_admins_still_need_a_company(): void
    {
        $this->actingAs($this->super)->post(route('users.store'), [
            'name' => 'Partner Person', 'username' => 'partner.person', 'email' => 'partner.person@example.test',
            'role' => 'user', 'company_id' => 'jms',
        ])->assertSessionHasErrors('company_id');
        $this->assertNull(User::where('email', 'partner.person@example.test')->first());

        $this->actingAs($this->super)->post(route('users.store'), [
            'name' => 'No Company', 'username' => 'no.company.admin', 'email' => 'no.company.admin@example.test',
            'role' => 'admin', 'company_id' => '',
        ])->assertSessionHasErrors('company_id');
    }

    public function test_super_admin_can_move_an_admin_into_the_jms_team(): void
    {
        $this->actingAs($this->super)->patch(route('users.update', $this->acmeAdmin), [
            'name' => $this->acmeAdmin->name, 'username' => $this->acmeAdmin->username, 'email' => $this->acmeAdmin->email,
            'role' => 'admin', 'company_id' => 'jms',
        ])->assertSessionHasNoErrors();

        $moved = $this->acmeAdmin->fresh();
        $this->assertNull($moved->company_id);
        $this->assertTrue($moved->isJmsAdmin());
    }
}
