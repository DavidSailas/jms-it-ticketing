<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $email): User { return User::where('email', $email)->firstOrFail(); }

    private function ticket(array $o = []): Ticket
    {
        $user = $this->u('user@jmsoneit.com');

        return Ticket::create(array_merge([
            'user_id' => $user->id, 'subject' => 'Printer down', 'description' => 'x', 'category' => 'Hardware', 'priority' => 'high', 'status' => 'open',
        ], $o));
    }

    public function test_only_super_admin_can_open_reports_and_it_is_in_their_sidebar(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['user@jmsoneit.com', 'itsupport@jmsoneit.com', 'admin@jmsoneit.com'] as $e) {
            $this->actingAs($this->u($e))->get('/reports')->assertForbidden();
            $this->actingAs($this->u($e))->get('/reports/export')->assertForbidden();
        }

        $super = $this->u('superadmin@jmsoneit.com');
        $this->actingAs($super)->get('/reports')->assertOk()->assertSee('Service desk report');
        $this->actingAs($super)->get('/dashboard')->assertOk()->assertSee(route('reports.index'), false);
        $this->actingAs($this->u('admin@jmsoneit.com'))->get('/dashboard')->assertOk()->assertDontSee(route('reports.index'), false);
    }

    public function test_every_period_renders_with_and_without_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $super = $this->u('superadmin@jmsoneit.com');
        $eng   = $this->u('itsupport@jmsoneit.com');

        foreach (['this_month', 'last_month', 'this_quarter', 'this_year', 'last_year', 'all_time'] as $r) {
            $this->actingAs($super)->get("/reports?range={$r}")->assertOk();
        }

        $this->ticket(['status' => 'resolved', 'assigned_to' => $eng->id, 'resolved_at' => now()->addHours(2), 'rating' => 5, 'support_type' => 'remote']);
        $this->ticket(['status' => 'cancelled']);
        $this->ticket(['status' => 'in_progress', 'assigned_to' => $eng->id, 'priority' => 'critical']);
        Ticket::query()->where('status', 'open')->update(['created_at' => now()->subYear()]);

        foreach (['this_month', 'this_year', 'all_time', 'last_year'] as $r) {
            $this->actingAs($super)->get("/reports?range={$r}")->assertOk();
        }

        $this->actingAs($super)->get('/reports?range=this_month')->assertOk()
            ->assertSee('Tickets received')->assertSee('Engineer performance')->assertSee('IT Support')->assertSee('5 / 5');
    }

    public function test_custom_range_handles_swapped_empty_and_invalid_dates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $super = $this->u('superadmin@jmsoneit.com');
        $this->ticket();

        $this->actingAs($super)->get('/reports?range=custom&from=' . now()->addDay()->format('Y-m-d') . '&to=' . now()->subDay()->format('Y-m-d'))->assertOk()->assertSee('Tickets received');
        $this->actingAs($super)->get('/reports?range=custom&from=not-a-date')->assertOk();
        $this->actingAs($super)->get('/reports?range=custom')->assertOk();
        $this->actingAs($super)->get('/reports?range=bogus')->assertOk()->assertSee(now()->format('F Y'));
    }

    public function test_numbers_are_right_and_cancelled_tickets_are_not_counted_as_open_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $super = $this->u('superadmin@jmsoneit.com');
        $eng   = $this->u('itsupport@jmsoneit.com');

        $this->ticket(['status' => 'resolved', 'assigned_to' => $eng->id, 'priority' => 'low', 'resolved_at' => now()->addHours(5)]);
        $this->ticket(['status' => 'in_progress', 'assigned_to' => $eng->id]);
        $this->ticket(['status' => 'cancelled']);

        $res = $this->actingAs($super)->get('/reports?range=this_month')->assertOk();
        $v   = $res->viewData('stats');

        $this->assertSame(3, $v['total']);
        $this->assertSame(1, $v['cancelled']);
        $this->assertSame(1, $v['done']);
        $this->assertSame(50, $v['rate']);   // 1 of the 2 tickets that were not cancelled
        $this->assertSame(1, $v['active']);  // the cancelled ticket is not active work
        $this->assertSame(100, $v['metPct']);
        $this->assertEqualsWithDelta(5.0, $v['avgHours'], 0.1);
        $this->assertSame(0, $res->viewData('now')['waiting']);
    }

    public function test_excel_export_lists_the_tickets_of_the_period(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->ticket(['subject' => 'Wi-Fi keeps dropping']);
        $old = $this->ticket(['subject' => 'Last year problem']);
        $old->forceFill(['created_at' => now()->subYears(2)])->save();

        $res = $this->actingAs($this->u('superadmin@jmsoneit.com'))->get('/reports/export?range=this_month');
        $res->assertOk();
        $this->assertStringContainsString('.xlsx', $res->headers->get('Content-Disposition'));

        // The export is an .xlsx workbook (a zip of XML files), so open it and read the sheet.
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $res->streamedContent());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file) === true, 'The export is not a valid .xlsx file.');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($file);

        $this->assertStringContainsString('Wi-Fi keeps dropping', $sheet);
        $this->assertStringNotContainsString('Last year problem', $sheet);
        foreach (['Ticket', 'Subject', 'Requester'] as $heading) {
            $this->assertStringContainsString('>' . $heading . '<', $sheet);
        }
    }
}
