<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only JMS dispatches JMS engineers. A partner that cannot solve a ticket asks JMS to take over; the ticket then
 * waits, flagged "JMS requested", for a JMS admin or super admin to accept it.
 */
class JmsRequestTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private Company $globex;
    private User $super;
    private User $jmsAdmin;
    private User $acmeAdmin;
    private User $acmeUser;
    private User $acmeEngineer;
    private User $otherAcmeEngineer;
    private User $globexAdmin;
    private User $jmsEngineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme   = Company::create(['name' => 'Acme Corp']);
        $this->globex = Company::create(['name' => 'Globex Ltd']);

        $this->super             = $this->person('super_admin', null, 'Boss Super');
        $this->jmsAdmin          = $this->person('admin', null, 'Jun JMS Admin');
        $this->acmeAdmin         = $this->person('admin', $this->acme, 'Acme Admin');
        $this->acmeUser          = $this->person('user', $this->acme, 'Acme Staff');
        $this->acmeEngineer      = $this->person('it_support', $this->acme, 'Acme Own Engineer');
        $this->otherAcmeEngineer = $this->person('it_support', $this->acme, 'Acme Second Engineer');
        $this->globexAdmin       = $this->person('admin', $this->globex, 'Globex Admin'); // Globex has no IT engineer
        $this->jmsEngineer       = $this->person('it_support', null, 'Jun JMS Engineer');
    }

    private function person(string $role, ?Company $company, string $name): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'name' => $name, 'role' => $role, 'company_id' => $company?->id,
            'username' => 'jmsreq' . $n, 'email' => "jmsreq{$n}@example.test",
        ]);
    }

    private function ticket(User $by, string $subject, array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'user_id' => $by->id, 'subject' => $subject, 'description' => 'Something is not working properly.',
            'category' => 'Server / Infrastructure', 'priority' => 'high', 'status' => 'open',
        ], $extra));
    }

    private function handledByAcmeIt(string $subject = 'Server will not boot'): Ticket
    {
        return $this->ticket($this->acmeUser, $subject, ['assigned_to' => $this->acmeEngineer->id, 'status' => 'assigned']);
    }

    private function fresh(Ticket $t): Ticket
    {
        return Ticket::withoutGlobalScopes()->findOrFail($t->id);
    }

    private function ask(User $as, Ticket $t, string $note = 'We restarted it twice and checked the cables.')
    {
        return $this->actingAs($as)->post(route('tickets.request-jms', $t), ['jms_note' => $note]);
    }

    private function toldAbout(User $u, string $text): bool
    {
        return $u->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), $text));
    }

    public function test_a_partner_admin_hands_a_ticket_to_jms_without_assigning_an_engineer(): void
    {
        $t = $this->handledByAcmeIt();

        $this->ask($this->acmeAdmin, $t)->assertRedirect()->assertSessionHas('success');

        $t = $this->fresh($t);
        $this->assertNull($t->assigned_to, 'JMS picks the engineer, not the partner');
        $this->assertSame('open', $t->status);
        $this->assertNotNull($t->jms_requested_at);
        $this->assertTrue($t->isAskingForJms());
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $t->id, 'is_internal' => false]);
        $this->assertStringContainsString('Support requested from JMS', $t->comments()->first()->body);
    }

    public function test_the_company_engineer_holding_the_ticket_can_ask_but_other_engineers_cannot(): void
    {
        $t = $this->handledByAcmeIt();

        $this->ask($this->otherAcmeEngineer, $t)->assertForbidden();
        $this->assertFalse($this->fresh($t)->isAskingForJms());

        $this->ask($this->acmeEngineer, $t)->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($this->fresh($t)->isAskingForJms());
    }

    public function test_only_the_jms_team_is_alerted_and_the_requester_is_told(): void
    {
        $t = $this->handledByAcmeIt();
        $this->ask($this->acmeAdmin, $t);

        $this->assertTrue($this->toldAbout($this->super, 'JMS support requested'));
        $this->assertTrue($this->toldAbout($this->jmsAdmin, 'JMS support requested'));
        $this->assertTrue($this->toldAbout($this->acmeUser, 'passed to JMS support'));
        $this->assertFalse($this->toldAbout($this->jmsEngineer, 'JMS support requested'), 'no engineer is assigned yet');
    }

    public function test_it_needs_a_note_so_jms_knows_what_was_tried(): void
    {
        $t = $this->handledByAcmeIt();

        $this->ask($this->acmeAdmin, $t, '')->assertSessionHasErrors('jms_note');
        $this->ask($this->acmeAdmin, $t, 'short')->assertSessionHasErrors('jms_note');
        $this->assertFalse($this->fresh($t)->isAskingForJms());
    }

    public function test_partner_staff_other_companies_and_jms_itself_cannot_use_it(): void
    {
        $t = $this->handledByAcmeIt();

        $this->ask($this->acmeUser, $t)->assertForbidden();
        $this->ask($this->jmsAdmin, $t)->assertForbidden();   // JMS assigns directly, no request needed
        $this->ask($this->super, $t)->assertForbidden();

        $status = $this->ask($this->globexAdmin, $t)->getStatusCode();
        $this->assertContains($status, [403, 404]);

        $this->assertFalse($this->fresh($t)->isAskingForJms());
    }

    public function test_it_cannot_be_used_when_jms_already_has_the_ticket_or_twice(): void
    {
        $t = $this->handledByAcmeIt();
        $this->ask($this->acmeAdmin, $t);
        $this->ask($this->acmeAdmin, $t)->assertSessionHas('error');   // already asked

        $this->actingAs($this->jmsAdmin)->post(route('tickets.assign', $t), ['assigned_to' => $this->jmsEngineer->id, 'support_type' => 'onsite', 'priority' => 'high']);
        $this->ask($this->acmeAdmin, $t)->assertSessionHas('error');   // JMS is on it now
        $this->assertSame($this->jmsEngineer->id, (int) $this->fresh($t)->assigned_to);
    }

    public function test_a_jms_admin_accepting_it_clears_the_request(): void
    {
        $t = $this->handledByAcmeIt();
        $this->ask($this->acmeAdmin, $t);

        $this->actingAs($this->jmsAdmin)->post(route('tickets.assign', $t), ['assigned_to' => $this->jmsEngineer->id, 'support_type' => 'onsite', 'priority' => 'high'])
            ->assertSessionHasNoErrors();

        $t = $this->fresh($t);
        $this->assertNull($t->jms_requested_at);
        $this->assertFalse($t->isAskingForJms());
        $this->assertSame($this->jmsEngineer->id, (int) $t->assigned_to);
    }

    public function test_the_partner_admin_can_take_it_back_by_assigning_their_own_engineer(): void
    {
        $t = $this->handledByAcmeIt();
        $this->ask($this->acmeAdmin, $t);

        $this->actingAs($this->acmeAdmin)->post(route('tickets.assign', $t), ['assigned_to' => $this->otherAcmeEngineer->id, 'support_type' => 'remote', 'priority' => 'high'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->fresh($t)->isAskingForJms());
    }

    public function test_jms_sees_the_flag_in_the_list_and_on_the_ticket_and_the_partner_sees_it_is_waiting(): void
    {
        $t = $this->handledByAcmeIt('Server will not boot');
        $this->ask($this->acmeAdmin, $t);

        $this->actingAs($this->super)->get(route('tickets.index'))->assertOk()->assertSee('JMS requested');
        $this->actingAs($this->super)->get(route('tickets.show', $t))->assertOk()->assertSee('JMS support was requested')->assertSee('Accept & assign');
        $this->actingAs($this->acmeAdmin)->get(route('tickets.show', $t))->assertOk()->assertSee('Waiting for JMS support')->assertDontSee('Request JMS support');
    }

    public function test_the_request_box_is_offered_to_the_right_people_only(): void
    {
        $t = $this->handledByAcmeIt();

        $this->actingAs($this->acmeAdmin)->get(route('tickets.show', $t))->assertOk()->assertSee('Need JMS support?');
        $this->actingAs($this->acmeEngineer)->get(route('tickets.show', $t))->assertOk()->assertSee('Need JMS support?');
        $this->actingAs($this->super)->get(route('tickets.show', $t))->assertOk()->assertDontSee('Need JMS support?');
        $this->actingAs($this->acmeUser)->get(route('tickets.show', $t))->assertOk()->assertDontSee('Need JMS support?');
    }

    public function test_a_partner_admin_without_an_it_team_leaves_dispatching_to_jms(): void
    {
        $t = $this->ticket($this->person('user', $this->globex, 'Globex Staff'), 'Printer dead');

        $this->actingAs($this->globexAdmin)->get(route('tickets.show', $t))
            ->assertOk()->assertSee('JMS will assign an engineer')->assertDontSee('Accept & assign');

        $this->actingAs($this->globexAdmin)->post(route('tickets.assign', $t), ['assigned_to' => $this->jmsEngineer->id, 'support_type' => 'remote', 'priority' => 'high'])
            ->assertSessionHasErrors('assigned_to');
        $this->assertNull($this->fresh($t)->assigned_to);

        // JMS still sees the normal accept-and-assign form for it.
        $this->actingAs($this->super)->get(route('tickets.show', $t))->assertOk()->assertSee('Accept & assign');
    }
}
