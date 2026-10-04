<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleLookTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $email): User { return User::where('email', $email)->firstOrFail(); }

    public function test_admin_and_super_admin_get_their_own_look_but_share_the_same_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');
        $super = $this->u('superadmin@jmsoneit.com');

        // Admin: violet "Admin Console" shell and banner, no super-admin extras.
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Admin Console')->assertSee('Service desk')->assertSee('bg-violet-50', false)
            ->assertSee('Accept new tickets, assign your engineers')->assertSee('Upcoming bookings')->assertSee('Skip to content')
            ->assertDontSee('Super Admin Console')->assertDontSee('System overview')->assertDontSee('Recent system activity');

        // Super Admin: dark navy and gold shell, system overview and a system-wide activity feed.
        $this->actingAs($super)->get('/dashboard')->assertOk()
            ->assertSee('Super Admin Console')->assertSee('Administration')->assertSee('bg-brand-900', false)
            ->assertSee('System overview')->assertSee('Accounts by role')->assertSee('Recent system activity')
            ->assertSee('full control of the system');

        // Same working pages for both.
        foreach ([$admin, $super] as $x) {
            foreach (['/tickets', '/tickets/create', '/users', '/schedule', '/profile'] as $url) {
                $this->actingAs($x)->get($url)->assertOk();
            }
        }
    }

    public function test_other_roles_keep_the_standard_look(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['user@jmsoneit.com', 'itsupport@jmsoneit.com'] as $email) {
            $this->actingAs($this->u($email))->get('/dashboard')->assertOk()
                ->assertDontSee('Admin Console')->assertDontSee('Super Admin Console')->assertDontSee('System overview');
        }
    }

    public function test_system_activity_is_paged_at_15_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $super = $this->u('superadmin@jmsoneit.com');

        $log = fn (int $n) => collect(range(1, $n))->each(fn ($i) => ActivityLog::create([
            'user_id' => $super->id, 'category' => 'profile', 'action' => 'profile_updated', 'description' => "Entry number {$i}",
        ]));

        // Up to 15 entries: everything on one page, no pager.
        $log(15);
        $this->actingAs($super)->get('/dashboard')->assertOk()
            ->assertSee('Showing all')->assertDontSee('aria-label="Pagination"', false);

        // The 16th entry brings the pager in.
        $log(1);
        $this->actingAs($super)->get('/dashboard')->assertOk()
            ->assertSee('aria-label="Pagination"', false)->assertSee('1&ndash;15', false);
        $this->actingAs($super)->get('/dashboard?activity_page=2')->assertOk()
            ->assertSee('16&ndash;16', false);
    }

    public function test_dashboard_shows_overdue_tickets_and_upcoming_bookings_to_admins(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin   = $this->u('admin@jmsoneit.com');
        $partner = $this->u('user@jmsoneit.com');
        $make = fn (array $x) => \App\Models\Ticket::create($x + [
            'user_id' => $partner->id, 'description' => 'Created for the dashboard test', 'category' => 'Hardware', 'priority' => 'critical', 'status' => 'open',
        ]);

        // Nothing overdue or booked yet.
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertDontSee('Needs attention')->assertSee('Nothing booked');

        $late = $make(['subject' => 'Server room is down']);
        $late->forceFill(['created_at' => now()->subDays(2)])->save();
        $make(['subject' => 'Bring a spare monitor', 'priority' => 'low', 'scheduled_for' => now()->addDay()->setTime(9, 0)]);

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Needs attention')->assertSee('Server room is down')
            ->assertSee('Bring a spare monitor')->assertDontSee('Nothing booked');
    }
}
