<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /**
     * Named date ranges an admin can report on, in the order shown in the
     * picker. "This month" is the default landing view.
     */
    private const RANGES = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_30' => 'Last 30 days',
        'last_90' => 'Last 90 days',
        'all_time' => 'All time',
        'custom' => 'Custom range',
    ];

    public function index(Request $request)
    {
        [$from, $to, $rangeLabel] = $this->resolveRange($request);
        $report = $this->buildReport($from, $to);

        return view('admin.reports.index', array_merge(
            $report,
            [
                'ranges' => self::RANGES,
                'selectedRange' => $request->query('range', 'this_month'),
                'rangeLabel' => $rangeLabel,
                'from' => $from,
                'to' => $to,
                'customFrom' => $request->query('from'),
                'customTo' => $request->query('to'),
                'comparison' => $this->previousPeriodComparison($from, $to, $report),
                'summary' => $this->narrativeSummary($rangeLabel, $report),
            ]
        ));
    }

    public function exportPdf(Request $request)
    {
        [$from, $to, $rangeLabel] = $this->resolveRange($request);
        $report = $this->buildReport($from, $to);

        $data = array_merge($report, [
            'rangeLabel' => $rangeLabel,
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'preparedBy' => $request->user()->name,
            'comparison' => $this->previousPeriodComparison($from, $to, $report),
            'summary' => $this->narrativeSummary($rangeLabel, $report),
            'logoData' => $this->logoDataUri(),
        ]);

        $pdf = Pdf::loadView('admin.reports.export-pdf', $data)->setPaper('a4', 'portrait');

        $filename = 'it-report-'.\Illuminate\Support\Str::slug($rangeLabel).'-'.now()->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * How this period compares to the equivalent period immediately before
     * it (same length, shifted back) — e.g. this month vs. last month. Null
     * for "all time", where there's no equivalent prior window.
     */
    private function previousPeriodComparison(?Carbon $from, ?Carbon $to, array $report): ?array
    {
        if (! $from || ! $to) {
            return null;
        }

        $lengthDays = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($lengthDays - 1)->startOfDay();

        $prevTickets = Ticket::where('created_at', '>=', $prevFrom)->where('created_at', '<=', $prevTo)->count();
        $prevResolved = Ticket::where('created_at', '>=', $prevFrom)->where('created_at', '<=', $prevTo)
            ->whereIn('status', ['resolved', 'closed'])->count();
        $prevRate = $prevTickets > 0 ? round(($prevResolved / $prevTickets) * 100) : null;

        return [
            'label' => 'vs. previous period',
            'ticketsDelta' => $this->percentDelta($prevTickets, $report['totalTickets']),
            'rateDelta' => $report['resolutionRate'] !== null && $prevRate !== null
                ? $report['resolutionRate'] - $prevRate
                : null,
        ];
    }

    /**
     * Percent change from $before to $after, direction-aware (null when
     * there's nothing to compare against — e.g. zero tickets in both).
     */
    private function percentDelta(int $before, int $after): ?float
    {
        if ($before === 0) {
            return $after > 0 ? 100.0 : null;
        }

        return round((($after - $before) / $before) * 100);
    }

    /**
     * A short, plain-English paragraph summarizing the period — the kind of
     * line an admin would otherwise have to write by hand before forwarding
     * this report to a manager.
     */
    private function narrativeSummary(string $rangeLabel, array $report): string
    {
        if ($report['totalTickets'] === 0) {
            return "No tickets were created during {$rangeLabel}.";
        }

        $sentence = "During {$rangeLabel}, {$report['totalTickets']} ".
            ($report['totalTickets'] === 1 ? 'ticket was' : 'tickets were')." submitted";

        if ($report['resolutionRate'] !== null) {
            $sentence .= ", with {$report['resolutionRate']}% resolved or closed";
        }

        if ($report['avgResolutionHours'] !== null) {
            $sentence .= $report['avgResolutionHours'] < 24
                ? ' at an average resolution time of '.round($report['avgResolutionHours'], 1).' hours'
                : ' at an average resolution time of '.round($report['avgResolutionHours'] / 24, 1).' days';
        }

        $sentence .= '.';

        if ($report['unassignedInRange'] > 0) {
            $sentence .= " {$report['unassignedInRange']} ".
                ($report['unassignedInRange'] === 1 ? 'ticket remains' : 'tickets remain').' unassigned and need attention.';
        }

        return $sentence;
    }

    /**
     * The company logo as a data: URI, so it's embedded directly in the PDF
     * rather than requiring dompdf to resolve a filesystem/HTTP path.
     */
    private function logoDataUri(): ?string
    {
        $path = public_path('images/logo.png');

        if (! file_exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    /**
     * Turns the "range" query param (plus from/to for a custom range) into
     * a concrete [start, end] pair and a human label used in both the page
     * heading and the PDF cover.
     */
    private function resolveRange(Request $request): array
    {
        $range = $request->query('range', 'this_month');
        $now = now();

        switch ($range) {
            case 'last_month':
                $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $end = $now->copy()->subMonthNoOverflow()->endOfMonth();
                break;
            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'last_30':
                $start = $now->copy()->subDays(29)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'last_90':
                $start = $now->copy()->subDays(89)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'all_time':
                $start = null;
                $end = null;
                break;
            case 'custom':
                $start = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : $now->copy()->startOfMonth();
                $end = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : $now->copy()->endOfDay();
                break;
            case 'this_month':
            default:
                $range = 'this_month';
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
        }

        if ($range === 'all_time') {
            $label = 'All time';
        } elseif ($range === 'custom') {
            $label = $start->format('M j, Y').' – '.$end->format('M j, Y');
        } else {
            $label = self::RANGES[$range];
        }

        return [$start, $end, $label];
    }

    /**
     * Everything the report page and the PDF both need, built once so the
     * two never drift apart. $from/$to are null for "all time".
     */
    private function buildReport(?Carbon $from, ?Carbon $to): array
    {
        // Qualified with the table name throughout (tickets.created_at, not
        // just created_at) because byAgent below joins the users table,
        // which also has a created_at column — leaving it bare caused an
        // "ambiguous column" error once that join was in play.
        $ticketsInRange = Ticket::query()
            ->when($from, fn ($q) => $q->where('tickets.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('tickets.created_at', '<=', $to));

        $totalTickets = (clone $ticketsInRange)->count();

        $byStatus = (clone $ticketsInRange)->toBase()
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $byPriority = (clone $ticketsInRange)->toBase()
            ->selectRaw('priority, COUNT(*) as total')->groupBy('priority')->pluck('total', 'priority');

        $byCategory = (clone $ticketsInRange)->toBase()
            ->selectRaw('category, COUNT(*) as total')->groupBy('category')
            ->orderByDesc('total')->pluck('total', 'category');

        // Resolution time: only tickets that actually reached resolved/closed
        // in this window, so "average" doesn't get skewed by ones still open.
        // Computed in PHP (rather than a driver-specific SQL date function)
        // so this works the same on SQLite and MySQL.
        $resolvedRows = (clone $ticketsInRange)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        $avgResolutionHours = $resolvedRows->isNotEmpty()
            ? $resolvedRows->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60)
            : null;

        $closedCount = (clone $ticketsInRange)->where('status', 'closed')->count();
        $resolvedOrClosed = (clone $ticketsInRange)->whereIn('status', ['resolved', 'closed'])->count();

        // Workload per IT Support agent — tickets assigned to them that were
        // *created* in this window, so a busy month shows up as busy.
        $byAgent = (clone $ticketsInRange)->toBase()
            ->join('users', 'users.id', '=', 'tickets.assigned_to')
            ->selectRaw('users.name as agent, COUNT(*) as total')
            ->groupBy('users.name')->orderByDesc('total')->pluck('total', 'agent');

        $unassignedInRange = (clone $ticketsInRange)->whereNull('assigned_to')->count();

        // Volume trend — tickets created per day (short ranges) or per month
        // (year / all-time), so the line makes sense either way.
        $trend = $this->buildTrend($from, $to);

        // Assets: a snapshot as of now (assets don't really belong to a
        // "created in range" story the way tickets do — the inventory is
        // whatever's on hand today).
        $totalAssets = Asset::count();
        $assetsByType = Asset::toBase()->selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type');
        $assignedAssets = Asset::whereNotNull('user_id')->count();
        $unassignedAssets = $totalAssets - $assignedAssets;
        $assetsByStatus = Asset::toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $usersByRole = User::toBase()->selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');

        return [
            'totalTickets' => $totalTickets,
            'byStatus' => $this->labelPieData($byStatus, [
                'open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending',
                'resolved' => 'Resolved', 'closed' => 'Closed',
            ], [
                'open' => '#3b82f6', 'in_progress' => '#6366f1', 'pending' => '#f59e0b',
                'resolved' => '#10b981', 'closed' => '#9ca3af',
            ]),
            'byPriority' => $this->labelPieData($byPriority, [
                'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical',
            ], [
                'low' => '#9ca3af', 'medium' => '#3b82f6', 'high' => '#f59e0b', 'critical' => '#ef4444',
            ]),
            'byCategory' => $this->labelPieData($byCategory, null, null, true),
            'avgResolutionHours' => $avgResolutionHours,
            'closedCount' => $closedCount,
            'resolutionRate' => $totalTickets > 0 ? round(($resolvedOrClosed / $totalTickets) * 100) : null,
            'byAgent' => $byAgent,
            'unassignedInRange' => $unassignedInRange,
            'trend' => $trend,

            'totalAssets' => $totalAssets,
            'assignedAssets' => $assignedAssets,
            'unassignedAssets' => $unassignedAssets,
            'assetsByType' => $this->labelPieData($assetsByType, Asset::TYPES, null),
            'assetsByStatus' => $this->labelPieData($assetsByStatus, Asset::STATUSES, [
                'active' => '#10b981', 'in_repair' => '#f59e0b', 'retired' => '#9ca3af',
            ]),

            'usersByRole' => $this->labelPieData($usersByRole, [
                'staff' => 'Staff', 'it_support' => 'IT Support', 'admin' => 'Admin',
            ], [
                'staff' => '#6366f1', 'it_support' => '#1a6b3c', 'admin' => '#b45309',
            ]),
        ];
    }

    /**
     * A rotating palette for facets with no fixed brand color (categories
     * are free text, so we can't hardcode one color per value).
     */
    private const PALETTE = ['#1a6b3c', '#3b82f6', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#ec4899', '#9ca3af'];

    /**
     * Normalizes a `pluck('total','key')` collection into the shape the pie
     * chart component and the PDF table both expect: label, value, color,
     * percent. $labels/$colors are optional lookup maps; when omitted the
     * raw key is title-cased and colors come from the rotating palette.
     */
    private function labelPieData($rows, ?array $labels, ?array $colors, bool $capOthers = false): array
    {
        $rows = collect($rows)->filter(fn ($v) => $v > 0);

        if ($capOthers && $rows->count() > 6) {
            $top = $rows->sortDesc()->take(5);
            $rest = $rows->sortDesc()->slice(5)->sum();
            $rows = $rest > 0 ? $top->put('Other', $rest) : $top;
        }

        $total = $rows->sum();
        $i = 0;

        return $rows->map(function ($value, $key) use ($labels, $colors, $total, &$i) {
            $label = $labels[$key] ?? (is_string($key) ? ucwords(str_replace(['_', '-'], ' ', $key)) : $key);
            $color = $colors[$key] ?? self::PALETTE[$i % count(self::PALETTE)];
            $i++;

            return [
                'label' => $label,
                'value' => (int) $value,
                'color' => $color,
                'percent' => $total > 0 ? round(($value / $total) * 100, 1) : 0,
            ];
        })->values()->all();
    }

    /**
     * Ticket volume over the window: daily buckets for a range of 3 months
     * or less, monthly buckets for anything longer (including all-time).
     */
    private function buildTrend(?Carbon $from, ?Carbon $to): array
    {
        $start = $from ?? Ticket::min('created_at');
        $end = $to ?? now();

        if (! $start) {
            return [];
        }

        $start = $start instanceof Carbon ? $start : Carbon::parse($start);
        $daily = $start->diffInDays($end) <= 92;

        // Grouped in PHP (rather than a driver-specific date-format SQL
        // function) so this works the same on SQLite and MySQL.
        $rows = Ticket::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->get(['created_at'])
            ->groupBy(fn ($t) => $daily ? $t->created_at->format('Y-m-d') : $t->created_at->format('Y-m'))
            ->map->count();

        $points = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $daily ? $cursor->format('Y-m-d') : $cursor->format('Y-m');
            $points[] = [
                'label' => $daily ? $cursor->format('M j') : $cursor->format('M Y'),
                'value' => (int) ($rows[$key] ?? 0),
            ];
            $cursor = $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow();
        }

        // Cap to the most recent 60 points so a multi-year "all time" range
        // doesn't render an unreadable wall of bars.
        return array_slice($points, -60);
    }
}
