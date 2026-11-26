<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IT Engineers can look at tickets that are still waiting for an admin, but only look: they cannot accept,
 * assign, reply to or change them, and tickets that are not waiting stay private to whoever they are assigned to.
 */
class AvailableTicketsTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $username): User { return User::where('username', $username)->firstOrFail(); }

    private function submit(string $subject): Ticket
    {
        $this->actingAs($this->u('user'))->post('/tickets', [
            'subject' => $subject, 'category' => 'Hardware', 'priority' => 'high', 'description' => "Details for {$subject}", 'when' => 'now',
        ])->assertRedirect();

        return Ticket::withoutGlobalScopes()->latest('id')->first();
    }

    private function assignTo(Ticket $ticket, User $engineer): void
    {
        $this->actingAs($this->u('admin'))->patch("/tickets/{$ticket->id}", ['status' => 'in_progress', 'priority' => 'high', 'assigned_to' => $engineer->id])->assertRedirect();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_list_shows_only_tickets_waiting_for_acceptance(): void
    {
        $waiting  = $this->submit('Printer jams every page');
        $taken    = $this->submit('Server room too hot');
        $this->assignTo($taken, $this->u('jmsengineer'));

        $this->actingAs($this->u('itsupport'))->get('/tickets/available')
            ->assertOk()
            ->assertSee('Printer jams every page')
            ->assertSee($waiting->ticket_no)
            ->assertSee('only an admin can accept a ticket')
            ->assertDontSee('Server room too hot');
    }

    public function test_an_engineer_can_read_a_waiting_ticket_but_is_offered_no_actions(): void
    {
        $ticket = $this->submit('Wi-Fi drops in the lobby');

        $this->actingAs($this->u('itsupport'))->get("/tickets/available/{$ticket->id}")
            ->assertOk()
            ->assertSee('View only')
            ->assertSee('Wi-Fi drops in the lobby')
            ->assertSee("Details for Wi-Fi drops in the lobby")
            ->assertDontSee('Accept &amp; assign', false)
            ->assertDontSee('Send reply')
            ->assertDontSee('Internal note');
    }

    public function test_an_engineer_cannot_accept_assign_reply_or_change_a_waiting_ticket(): void
    {
        $ticket = $this->submit('CCTV camera offline');
        $me = $this->u('itsupport');

        $this->actingAs($me)->post("/tickets/{$ticket->id}/assign", ['assigned_to' => $me->id, 'priority' => 'high', 'support_type' => 'remote'])->assertStatus(404);
        $this->actingAs($me)->post("/tickets/{$ticket->id}/comments", ['body' => 'I will take it'])->assertStatus(404);
        $this->actingAs($me)->post("/tickets/{$ticket->id}/progress", ['status' => 'in_progress'])->assertStatus(404);
        $this->actingAs($me)->patch("/tickets/{$ticket->id}", ['status' => 'in_progress', 'assigned_to' => $me->id])->assertStatus(404);

        // The normal ticket page is still closed to them (it is the working page), with the friendly explanation.
        $this->actingAs($me)->get("/tickets/{$ticket->id}")->assertNotFound()->assertSee('This ticket is not available to you');

        $fresh = Ticket::withoutGlobalScopes()->find($ticket->id);
        $this->assertNull($fresh->assigned_to);
        $this->assertSame('open', $fresh->status);
        $this->assertSame(0, $fresh->comments()->count());
    }

    public function test_a_ticket_assigned_to_the_engineer_opens_on_the_normal_page(): void
    {
        $ticket = $this->submit('Laptop will not start');
        $me = $this->u('itsupport');
        $this->assignTo($ticket, $me);

        $this->actingAs($me)->get("/tickets/available/{$ticket->id}")->assertRedirect(route('tickets.show', $ticket));
        $this->actingAs($me)->get("/tickets/{$ticket->id}")->assertOk();
    }

    public function test_a_ticket_someone_else_already_has_is_not_viewable_here(): void
    {
        $ticket = $this->submit('Firewall rule change');
        $this->assignTo($ticket, $this->u('jmsengineer'));

        $this->actingAs($this->u('itsupport'))->get("/tickets/available/{$ticket->id}")
            ->assertRedirect(route('tickets.available'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, $ticket->ticket_no));
    }

    public function test_only_it_engineers_use_these_pages(): void
    {
        $ticket = $this->submit('Email not syncing');

        foreach (['user', 'admin', 'superadmin'] as $who) {
            $this->actingAs($this->u($who))->get('/tickets/available')->assertForbidden();
            $this->actingAs($this->u($who))->get("/tickets/available/{$ticket->id}")->assertForbidden();
        }

        auth()->logout();
        $this->get('/tickets/available')->assertRedirect('/login');
    }

    public function test_the_menu_shows_the_link_and_count_to_engineers_only(): void
    {
        $this->submit('First waiting ticket');
        $this->submit('Second waiting ticket');

        $this->actingAs($this->u('itsupport'))->get('/dashboard')
            ->assertOk()->assertSee('Available Tickets')->assertSee('2 waiting for acceptance');

        $this->actingAs($this->u('admin'))->get('/dashboard')->assertOk()->assertDontSee('Available Tickets');
        $this->actingAs($this->u('user'))->get('/dashboard')->assertOk()->assertDontSee('Available Tickets');
    }

    public function test_notifications_for_tickets_you_can_no_longer_open_are_flagged(): void
    {
        $ticket = $this->submit('UPS beeping');
        $me = $this->u('itsupport');
        $this->assignTo($ticket, $me);
        $this->assertFalse($me->fresh()->notificationFeed()['items'][0]['unavailable']);

        $this->assignTo($ticket, $this->u('jmsengineer'));   // taken away from them

        $items = $this->actingAs($me)->getJson('/notifications/feed')->assertOk()->json('items');
        $this->assertTrue(collect($items)->firstWhere('kind', 'assigned')['unavailable']);

        $this->actingAs($me)->get('/notifications')->assertOk()->assertSee('No longer available to you');
    }

    public function test_a_ticket_that_went_back_to_waiting_opens_in_the_read_only_view(): void
    {
        $ticket = $this->submit('Switch port dead');
        $me = $this->u('itsupport');
        $this->assignTo($ticket, $me);
        $note = $me->fresh()->notifications->first(fn ($n) => ($n->data['kind'] ?? null) === 'assigned');

        // An admin pulls it back to the waiting queue.
        Ticket::withoutGlobalScopes()->whereKey($ticket->id)->update(['assigned_to' => null, 'status' => 'open']);

        $this->actingAs($me)->get("/notifications/{$note->id}/open")->assertRedirect(route('tickets.preview', $ticket->id));
        $this->assertFalse($me->fresh()->notificationFeed()['items'][0]['unavailable']);   // viewable, so not "gone"
    }
}
