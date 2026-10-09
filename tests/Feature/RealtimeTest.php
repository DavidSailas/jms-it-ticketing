<?php

namespace Tests\Feature;

use App\Events\NotificationPushed;
use App\Events\TicketChanged;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Live updates: off by default, and when on, only the right people's channels are told. */
class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private User $partner;
    private User $acmeAdmin;
    private User $engineer;
    private User $oldEngineer;
    private User $super;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme        = Company::create(['name' => 'Acme Corp']);
        $this->partner     = User::factory()->create(['role' => 'user', 'company_id' => $this->acme->id]);
        $this->acmeAdmin   = User::factory()->create(['role' => 'admin', 'company_id' => $this->acme->id]);
        $this->engineer    = User::factory()->create(['role' => 'it_support', 'name' => 'Ed Engineer']);
        $this->oldEngineer = User::factory()->create(['role' => 'it_support', 'name' => 'Olga Old']);
        $this->super       = User::factory()->create(['role' => 'super_admin']);

        $this->ticket = Ticket::create([
            'user_id' => $this->partner->id, 'subject' => 'Printer down', 'description' => 'It will not print.',
            'category' => 'Hardware', 'priority' => 'medium', 'status' => 'assigned', 'assigned_to' => $this->engineer->id,
        ]);
    }

    private function turnOn(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.client' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http'],
        ]);
    }

    public function test_nothing_is_broadcast_or_loaded_while_it_is_off(): void
    {
        Event::fake([TicketChanged::class, NotificationPushed::class]);

        $this->actingAs($this->engineer)->post(route('tickets.comment', $this->ticket), ['body' => 'On it.'])->assertRedirect();

        Event::assertNotDispatched(TicketChanged::class);
        Event::assertNotDispatched(NotificationPushed::class);
        $this->actingAs($this->engineer)->get(route('dashboard'))->assertOk()->assertDontSee('JMS_REALTIME');
    }

    public function test_a_reply_nudges_the_ticket_owner_company_admins_jms_and_the_engineer(): void
    {
        $this->turnOn();
        Event::fake([TicketChanged::class, NotificationPushed::class]);

        $this->actingAs($this->engineer)->post(route('tickets.comment', $this->ticket), ['body' => 'On it.'])->assertRedirect();

        Event::assertDispatched(TicketChanged::class, function (TicketChanged $e) {
            return $e->payload['ticket_id'] === $this->ticket->id
                && $e->payload['kind'] === 'comment'
                && $e->payload['actor_id'] === $this->engineer->id
                && ! isset($e->payload['subject'])
                && collect(['staff.jms', "company.{$this->acme->id}", "user.{$this->partner->id}", "user.{$this->engineer->id}"])
                    ->diff($e->channels)->isEmpty();
        });
        Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $e) => in_array($this->partner->id, $e->userIds, true));
    }

    public function test_an_internal_note_never_nudges_the_requester(): void
    {
        $this->turnOn();
        Event::fake([TicketChanged::class, NotificationPushed::class]);

        // An admin leaves a staff-only note: the engineer is told, the requester must not be.
        $this->actingAs($this->super)->post(route('tickets.comment', $this->ticket), ['body' => 'Careful, VIP.', 'is_internal' => 1])->assertRedirect();

        Event::assertDispatched(TicketChanged::class, function (TicketChanged $e) {
            return ! in_array("user.{$this->partner->id}", $e->channels, true)
                && in_array('staff.jms', $e->channels, true)
                && in_array("user.{$this->engineer->id}", $e->channels, true);
        });
        Event::assertDispatched(NotificationPushed::class, fn (NotificationPushed $e) => in_array($this->engineer->id, $e->userIds, true)
            && ! in_array($this->partner->id, $e->userIds, true));
    }

    public function test_the_engineer_who_lost_a_ticket_is_told_too(): void
    {
        $this->turnOn();
        Event::fake([TicketChanged::class, NotificationPushed::class]);

        $this->ticket->update(['assigned_to' => $this->oldEngineer->id]);
        \App\Support\Notifier::ticketUpdated($this->ticket->fresh(), $this->super, ['status' => 'assigned', 'priority' => 'medium', 'assigned_to' => $this->engineer->id]);

        Event::assertDispatched(TicketChanged::class, fn (TicketChanged $e) => in_array("user.{$this->engineer->id}", $e->channels, true)
            && in_array("user.{$this->oldEngineer->id}", $e->channels, true));
    }

    public function test_a_broadcast_failure_never_breaks_the_action(): void
    {
        $this->turnOn();
        // No Reverb server is running in the tests, so a real broadcast fails: the reply must still be saved.
        config(['broadcasting.connections.reverb' => array_merge(config('broadcasting.connections.reverb'), [
            'secret' => 's', 'app_id' => '1', 'options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
            'client_options' => ['timeout' => 1, 'connect_timeout' => 1],
        ])]);

        $this->actingAs($this->engineer)->post(route('tickets.comment', $this->ticket), ['body' => 'Still saved.'])->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', ['body' => 'Still saved.']);
    }

    public function test_channel_rules(): void
    {
        $this->assertTrue(Realtime::canListen($this->partner, 'user', $this->partner->id));
        $this->assertFalse(Realtime::canListen($this->partner, 'user', $this->engineer->id));

        $this->assertTrue(Realtime::canListen($this->acmeAdmin, 'company', $this->acme->id));
        $this->assertFalse(Realtime::canListen($this->partner, 'company', $this->acme->id), 'ordinary partner staff only get their own tickets');
        $this->assertFalse(Realtime::canListen($this->acmeAdmin, 'company', $this->acme->id + 1));

        $this->assertTrue(Realtime::canListen($this->super, 'staff'));
        $this->assertFalse(Realtime::canListen($this->acmeAdmin, 'staff'));
        $this->assertFalse(Realtime::canListen($this->engineer, 'staff'));
        $this->assertFalse(Realtime::canListen($this->super, 'anything-else'));
    }

    public function test_the_browser_only_gets_its_own_channels(): void
    {
        $this->turnOn();

        $partner = Realtime::clientConfig($this->partner);
        $this->assertSame(["user.{$this->partner->id}"], $partner['channels']);

        $admin = Realtime::clientConfig($this->acmeAdmin);
        $this->assertSame(["user.{$this->acmeAdmin->id}", "company.{$this->acme->id}"], $admin['channels']);

        $this->assertContains('staff.jms', Realtime::clientConfig($this->super)['channels']);
        $this->assertArrayNotHasKey('secret', $partner);

        $this->actingAs($this->engineer)->get(route('dashboard'))->assertOk()->assertSee('JMS_REALTIME');
    }

    public function test_the_board_has_a_json_feed_limited_to_what_the_person_may_see(): void
    {
        Ticket::create([
            'user_id' => $this->partner->id, 'subject' => 'Not Ed\'s ticket', 'description' => 'x', 'category' => 'Hardware',
            'priority' => 'low', 'status' => 'assigned', 'assigned_to' => $this->oldEngineer->id,
        ]);

        $this->actingAs($this->engineer)->getJson(route('tickets.board.cards'))->assertOk()
            ->assertJsonCount(1, 'cards')->assertJsonPath('cards.0.no', $this->ticket->ticket_no);

        $this->actingAs($this->super)->getJson(route('tickets.board.cards'))->assertOk()->assertJsonCount(2, 'cards');
        $this->actingAs($this->partner)->getJson(route('tickets.board.cards'))->assertForbidden();
    }
}
