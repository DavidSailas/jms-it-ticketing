<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The live "Waiting for acceptance" queue shown to admins / super admins. */
class PendingTicketsFeedTest extends TestCase
{
    use RefreshDatabase;

    private function ticketFor(User $requester, array $attrs = []): Ticket
    {
        return Ticket::create(array_merge([
            'user_id'     => $requester->id,
            'subject'     => 'Printer is offline',
            'description' => 'The 2nd floor printer does not respond.',
            'category'    => 'Printer / Peripherals',
            'priority'    => 'medium',
        ], $attrs));
    }

    public function test_admin_sees_tickets_from_users_and_staff_waiting_for_acceptance(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $partner = User::factory()->create(['role' => 'user', 'company' => 'Acme']);
        $staff   = User::factory()->create(['role' => 'admin']);

        $this->ticketFor($partner);
        $this->ticketFor($staff, ['subject' => 'Staff laptop will not boot']);

        $this->actingAs($admin)->getJson(route('tickets.pending-feed'))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'items');
    }

    public function test_accepted_and_finished_tickets_are_not_waiting(): void
    {
        $admin    = User::factory()->create(['role' => 'super_admin']);
        $engineer = User::factory()->create(['role' => 'it_support']);
        $partner  = User::factory()->create();

        $waiting = $this->ticketFor($partner);
        $this->ticketFor($partner, ['status' => 'assigned', 'assigned_to' => $engineer->id]);
        $this->ticketFor($partner, ['status' => 'resolved']);
        $this->ticketFor($partner, ['status' => 'cancelled']);

        $this->actingAs($admin)->getJson(route('tickets.pending-feed'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.id', $waiting->id);
    }

    public function test_accepting_a_ticket_removes_it_from_the_next_poll(): void
    {
        $admin    = User::factory()->create(['role' => 'admin']);
        $engineer = User::factory()->create(['role' => 'it_support']);
        $ticket   = $this->ticketFor(User::factory()->create());

        $this->actingAs($admin)->getJson(route('tickets.pending-feed'))->assertJsonPath('count', 1);

        $this->actingAs($admin)->post(route('tickets.assign', $ticket), [
            'assigned_to' => $engineer->id, 'support_type' => 'remote', 'priority' => 'medium',
        ]);

        $this->actingAs($admin)->getJson(route('tickets.pending-feed'))
            ->assertJsonPath('count', 0)->assertJsonCount(0, 'items');
    }

    public function test_feed_reports_tickets_newer_than_what_the_browser_has_seen(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $partner = User::factory()->create();

        $seen = $this->ticketFor($partner);
        $new  = $this->ticketFor($partner, ['subject' => 'VPN keeps dropping']);

        $this->actingAs($admin)->getJson(route('tickets.pending-feed', ['after' => $seen->id]))
            ->assertOk()
            ->assertJsonPath('new_count', 1)
            ->assertJsonPath('new.0.id', $new->id)
            ->assertJsonPath('latest_id', $new->id);

        $this->actingAs($admin)->getJson(route('tickets.pending-feed', ['after' => $new->id]))
            ->assertJsonPath('new_count', 0);
    }

    public function test_most_urgent_ticket_is_listed_first(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $partner = User::factory()->create();

        $this->ticketFor($partner, ['priority' => 'low']);
        $critical = $this->ticketFor($partner, ['priority' => 'critical']);

        $this->actingAs($admin)->getJson(route('tickets.pending-feed'))
            ->assertJsonPath('items.0.id', $critical->id);
    }

    public function test_only_admins_can_read_the_feed(): void
    {
        $this->getJson(route('tickets.pending-feed'))->assertUnauthorized();

        foreach (['user', 'it_support'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('tickets.pending-feed'))
                ->assertForbidden();
        }
    }

    public function test_admin_dashboard_renders_the_live_panel(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Waiting for acceptance')
            ->assertSee('pending-feed');
    }
}
