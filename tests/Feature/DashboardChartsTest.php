<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The dashboard charts, their live refresh endpoint and the super admin's partner overview. */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private function partnerOf(Company $company, string $role = 'user'): User
    {
        return User::factory()->create(['role' => $role, 'company_id' => $company->id, 'company' => $company->name]);
    }

    private function ticket(User $by, array $attrs = []): Ticket
    {
        return Ticket::create($attrs + [
            'user_id' => $by->id, 'subject' => 'Printer is offline', 'description' => 'It does not respond.',
            'category' => 'Hardware', 'priority' => 'medium',
        ]);
    }

    public function test_only_admins_and_super_admins_can_load_the_chart_feed(): void
    {
        $acme = Company::create(['name' => 'Acme']);

        $this->getJson(route('dashboard.charts'))->assertUnauthorized();

        foreach (['user', 'it_support'] as $role) {
            $this->actingAs($this->partnerOf($acme, $role))->getJson(route('dashboard.charts'))->assertForbidden();
        }

        foreach (['admin', 'super_admin'] as $role) {
            $user = $role === 'admin' ? $this->partnerOf($acme, 'admin') : User::factory()->create(['role' => 'super_admin']);
            $this->actingAs($user)->getJson(route('dashboard.charts'))->assertOk()
                ->assertJsonStructure(['range', 'kpis' => ['unassigned', 'assigned', 'in_progress', 'urgent', 'overdue', 'resolved_week'],
                    'trend' => ['labels', 'full', 'created', 'resolved', 'totals'], 'status', 'categories']);
        }
    }

    public function test_each_range_has_the_right_number_of_points_and_unknown_ranges_fall_back_to_a_week(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);

        foreach (['7d' => 7, '30d' => 30, '12w' => 12] as $range => $points) {
            $this->actingAs($super)->getJson(route('dashboard.charts', ['range' => $range]))->assertOk()
                ->assertJsonPath('range', $range)->assertJsonCount($points, 'trend.labels')
                ->assertJsonCount($points, 'trend.created')->assertJsonCount($points, 'trend.resolved');
        }

        $this->actingAs($super)->getJson(route('dashboard.charts', ['range' => 'nonsense']))->assertOk()
            ->assertJsonPath('range', '7d')->assertJsonCount(7, 'trend.labels');
    }

    public function test_trend_counts_new_and_resolved_tickets_in_the_right_day(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $acme  = Company::create(['name' => 'Acme']);
        $user  = $this->partnerOf($acme);

        $this->ticket($user);                                                         // today
        $this->ticket($user, ['status' => 'resolved', 'resolved_at' => now()]);       // today, resolved today
        $old = $this->ticket($user);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();                // outside a 30 day window

        $res = $this->actingAs($super)->getJson(route('dashboard.charts', ['range' => '30d']))->assertOk();
        $res->assertJsonPath('trend.totals.created', 2)->assertJsonPath('trend.totals.resolved', 1)
            ->assertJsonPath('trend.created.29', 2)->assertJsonPath('trend.resolved.29', 1);

        // Weekly view puts today in the last bucket.
        $this->actingAs($super)->getJson(route('dashboard.charts', ['range' => '12w']))
            ->assertJsonPath('trend.created.11', 2);
    }

    public function test_status_rows_are_complete_and_carry_a_link_to_the_filtered_list(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $user  = $this->partnerOf(Company::create(['name' => 'Acme']));
        $this->ticket($user);
        $this->ticket($user, ['status' => 'resolved']);

        $res = $this->actingAs($super)->getJson(route('dashboard.charts'))->assertOk();
        $res->assertJsonCount(count(Ticket::STATUSES), 'status')
            ->assertJsonPath('status.0.key', 'open')->assertJsonPath('status.0.value', 1)
            ->assertJsonPath('status.0.url', route('tickets.index', ['status' => 'open']));
        $this->assertSame(1, collect($res->json('status'))->firstWhere('key', 'resolved')['value']);
        $res->assertJsonPath('categories.0.label', 'Hardware')->assertJsonPath('categories.0.value', 2);
    }

    public function test_a_partner_admin_only_gets_their_own_companys_numbers(): void
    {
        $acme   = Company::create(['name' => 'Acme']);
        $globex = Company::create(['name' => 'Globex']);
        $admin  = $this->partnerOf($acme, 'admin');

        $this->ticket($this->partnerOf($acme));
        $this->ticket($this->partnerOf($globex));
        $this->ticket($this->partnerOf($globex));

        $this->actingAs($admin)->getJson(route('dashboard.charts'))->assertOk()
            ->assertJsonPath('trend.totals.created', 1)->assertJsonPath('kpis.unassigned', 1);
    }

    public function test_dashboard_draws_the_chart_cards_with_the_first_data_already_in_the_page(): void
    {
        $acme  = Company::create(['name' => 'Acme']);
        $admin = $this->partnerOf($acme, 'admin');
        $this->ticket($this->partnerOf($acme));

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Ticket activity')->assertSee('Tickets by status')->assertSee('Top categories')
            ->assertSee('dashCharts(', false)->assertSee('x-count=', false);
    }

    public function test_only_the_super_admin_sees_the_partner_overview_and_it_ranks_the_most_urgent_first(): void
    {
        $quiet = Company::create(['name' => 'Quiet Co']);
        $late  = Company::create(['name' => 'Late Co']);
        Company::create(['name' => 'Calm Co']);   // nothing open: on track
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->ticket($this->partnerOf($quiet), ['priority' => 'low']);
        $overdue = $this->ticket($this->partnerOf($late), ['priority' => 'critical']);
        $overdue->forceFill(['created_at' => now()->subDays(3)])->save();

        $page = $this->actingAs($super)->get('/dashboard')->assertOk()->assertSee('Partner companies');

        // Look only inside the partner panel (the company names also appear in the waiting queue above it).
        $panel = \Illuminate\Support\Str::after($page->getContent(), 'id="partners-title"');
        $panel = \Illuminate\Support\Str::before($panel, '</section>');
        $this->assertLessThan(strpos($panel, 'Quiet Co'), strpos($panel, 'Late Co'));
        $this->assertLessThan(strpos($panel, 'Calm Co'), strpos($panel, 'Quiet Co'));
        $this->assertStringContainsString('Needs attention', $panel);   // Late Co: past its SLA
        $this->assertStringContainsString('Keep an eye', $panel);       // Quiet Co: a ticket waits for acceptance
        $this->assertStringContainsString('On track', $panel);          // Calm Co

        $admin = $this->partnerOf($late, 'admin');
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertDontSee('Partner companies')->assertDontSee('Quiet Co');
    }

    public function test_partner_overview_invites_the_first_company_when_there_are_none(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($super)->get('/dashboard')->assertOk()
            ->assertSee('No partner companies yet')->assertSee('Add a company');
    }

    public function test_reports_page_uses_the_interactive_volume_chart(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->ticket($this->partnerOf(Company::create(['name' => 'Acme'])));

        $this->actingAs($super)->get(route('reports.index'))->assertOk()
            ->assertSee('reportTrend(', false)->assertSee('Ticket volume');
    }
}
