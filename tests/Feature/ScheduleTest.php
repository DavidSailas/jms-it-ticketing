<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $email): User { return User::where('email', $email)->firstOrFail(); }

    private function book(User $requester, string $subject, $when, ?User $engineer = null, string $status = 'assigned'): Ticket
    {
        return Ticket::create([
            'user_id' => $requester->id, 'assigned_to' => $engineer?->id, 'subject' => $subject,
            'description' => 'Booked visit for testing', 'category' => 'Hardware', 'priority' => 'medium',
            'status' => $status, 'scheduled_for' => $when, 'support_type' => 'onsite',
        ]);
    }

    public function test_only_staff_can_open_the_schedule_and_see_the_sidebar_link(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/schedule')->assertRedirect('/login');
        $this->actingAs($this->u('user@jmsoneit.com'))->get('/schedule')->assertForbidden();
        $this->actingAs($this->u('user@jmsoneit.com'))->get('/dashboard')->assertDontSee('href="' . route('schedule.index') . '"', false);

        foreach (['itsupport', 'admin', 'superadmin'] as $name) {
            $this->actingAs($this->u("{$name}@jmsoneit.com"))->get('/schedule')->assertOk()->assertSee('Schedule');
            $this->actingAs($this->u("{$name}@jmsoneit.com"))->get('/dashboard')->assertSee('href="' . route('schedule.index') . '"', false);
        }
    }

    public function test_bookings_appear_on_their_day_and_engineers_only_see_their_own(): void
    {
        $this->seed(DatabaseSeeder::class);
        $partner = $this->u('user@jmsoneit.com');
        $support = $this->u('itsupport@jmsoneit.com');
        $admin   = $this->u('admin@jmsoneit.com');
        $other   = User::factory()->create(['role' => 'it_support']);

        $when = now()->addDay()->setTime(14, 30);
        $this->book($partner, 'Replace the lobby switch', $when, $support);
        $this->book($partner, 'Configure the new printer', $when, $other);
        $this->book($partner, 'Unassigned onsite job', $when, null, 'open');
        $this->book($partner, 'Cancelled booking', $when, $support, 'cancelled');

        $month = $when->format('Y-m');

        // The engineer sees only what is assigned to them, and never cancelled bookings by default.
        $this->actingAs($support)->get("/schedule?month={$month}&date={$when->toDateString()}")->assertOk()
            ->assertSee('Replace the lobby switch')
            ->assertDontSee('Configure the new printer')
            ->assertDontSee('Unassigned onsite job')
            ->assertDontSee('Cancelled booking');

        // Each booking links straight to its ticket.
        $mine = Ticket::where('subject', 'Replace the lobby switch')->first();
        $this->actingAs($support)->get("/schedule?month={$month}&date={$when->toDateString()}")
            ->assertSee(route('tickets.show', $mine), false)->assertSee('View ticket');

        // Admins see everyone's bookings.
        $this->actingAs($admin)->get("/schedule?month={$month}&date={$when->toDateString()}")->assertOk()
            ->assertSee('Replace the lobby switch')
            ->assertSee('Configure the new printer')
            ->assertSee('Unassigned onsite job');

        // Engineer filter and the "unassigned" filter work for admins; engineers cannot widen their view.
        $this->actingAs($admin)->get("/schedule?month={$month}&engineer={$other->id}")->assertOk()
            ->assertSee('Configure the new printer')->assertDontSee('Replace the lobby switch');
        $this->actingAs($admin)->get("/schedule?month={$month}&engineer=unassigned")->assertOk()
            ->assertSee('Unassigned onsite job')->assertDontSee('Replace the lobby switch');
        $this->actingAs($support)->get("/schedule?month={$month}&engineer={$other->id}")->assertOk()
            ->assertDontSee('Configure the new printer');

        // Cancelled bookings only show when asked for.
        $this->actingAs($admin)->get("/schedule?month={$month}&status=cancelled")->assertOk()
            ->assertSee('Cancelled booking')->assertDontSee('Replace the lobby switch');
    }

    public function test_bad_input_falls_back_to_the_current_month(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');

        $this->actingAs($admin)->get('/schedule?month=not-a-month&date=2026-99-99&type=zzz&status=zzz&engineer=zzz')
            ->assertOk()->assertSee(now()->format('F Y'));
        $this->actingAs($admin)->get('/schedule?month=2027-02')->assertOk()->assertSee('February 2027');
    }
}
