<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Super admin reports: one page that answers "how did the service desk do in this period?"
 * for this month, last month, this quarter, this year, a custom range and so on.
 */
class ReportController extends Controller
{
    private const RANGES = [
        'this_month'   => 'This month',
        'last_month'   => 'Last month',
        'this_quarter' => 'This quarter',
        'this_year'    => 'This year',
        'last_year'    => 'Last year',
        'all_time'     => 'All time',
        'custom'       => 'Custom',
    ];

    public function index(Request $request)
    {
        [$key, $from, $to, $label] = $this->resolveRange($request);
        $tickets = $this->ticketsIn($from, $to);
        $stats   = $this->stats($tickets);

        $previous = $this->previousStats($from, $to);

        return view('reports.index', [
            'ranges'   => self::RANGES,
            'range'    => $key,
            'label'    => $label,
            'from'     => $from,
            'to'       => $to,
            'stats'    => $stats,
            'compare'  => $previous ? $this->compare($stats, $previous) : null,
            'trend'    => $this->trend($from, $to, $tickets),
            'status'   => $this->breakdown($tickets->countBy('status'), Ticket::STATUSES),
            'priority' => $this->breakdown($tickets->countBy('priority'), Ticket::PRIORITIES),
            'category' => $this->breakdown($tickets->countBy('category')->sortDesc(), null),
            'support'  => $tickets->whereNotNull('support_type')->countBy('support_type'),
            'engineers' => $this->engineers($tickets),
            'companies' => $this->companies($tickets),
            'now'      => $this->snapshot(),
            'accounts' => $this->accounts($from, $to),
            'summary'  => $this->summary($label, $stats),
        ]);
    }

    /** One row per ticket in the period, as an Excel (.xlsx) workbook. */
    public function export(Request $request)
    {
        [, $from, $to, $label] = $this->resolveRange($request);
        $tickets = Ticket::with(['user', 'assignee'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at')->get();

        $filename = 'ticket-report-' . \Illuminate\Support\Str::slug($label) . '-' . now()->format('Y-m-d') . '.xlsx';
        $headers  = ['Ticket', 'Subject', 'Requester', 'Company', 'Category', 'Priority', 'Status', 'Engineer', 'Support type',
                     'Created', 'Resolved', 'Hours to resolve', 'Target (hours)', 'Met target', 'Rating'];

        $rows = $tickets->map(function ($t) {
            $hours = $this->hoursToResolve($t);
            $met   = $this->metTarget($t);

            return [
                $t->ticket_no, $t->subject, $t->user?->name, $t->user?->company, $t->category, $t->priorityLabel(), $t->statusLabel(),
                $t->assignee?->name, $t->supportTypeLabel(), $t->created_at->format('Y-m-d H:i'), $t->resolved_at?->format('Y-m-d H:i'),
                $hours !== null ? round($hours, 1) : null, Ticket::SLA_HOURS[$t->priority] ?? null,
                $met === null ? null : ($met ? 'Yes' : 'No'), $t->rating !== null ? (int) $t->rating : null,
            ];
        });

        $xlsx = XlsxWriter::build('Tickets', $headers, $rows, [14, 40, 22, 24, 16, 11, 14, 22, 14, 17, 17, 16, 14, 11, 9]);

        return response()->streamDownload(fn () => print($xlsx), $filename, ['Content-Type' => XlsxWriter::MIME]);
    }

    // ---- Period --------------------------------------------------------------

    /** @return array{0:string,1:?Carbon,2:?Carbon,3:string} [range key, start, end, label] */
    private function resolveRange(Request $request): array
    {
        $key = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : 'this_month';
        $now = now();

        [$from, $to] = match ($key) {
            'last_month'   => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'    => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all_time'     => [null, null],
            'custom'       => $this->customRange($request),
            default        => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $label = match (true) {
            $key === 'all_time' => 'All time',
            $key === 'this_month' => $from->format('F Y'),
            $key === 'last_month' => $from->format('F Y'),
            $key === 'this_year', $key === 'last_year' => $from->format('Y'),
            $key === 'this_quarter' => 'Q' . $from->quarter . ' ' . $from->format('Y'),
            default => $from->isSameDay($to) ? $from->format('M j, Y') : $from->format('M j, Y') . ' to ' . $to->format('M j, Y'),
        };

        return [$key, $from, $to, $label];
    }

    private function customRange(Request $request): array
    {
        try {
            $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
            $to   = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        return $from->gt($to) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    // ---- Numbers -------------------------------------------------------------

    private function ticketsIn(?Carbon $from, ?Carbon $to): Collection
    {
        return Ticket::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->with('user:id,name,company')
            ->get();
    }

    private function hoursToResolve(Ticket $t): ?float
    {
        return $t->resolved_at ? $t->created_at->diffInMinutes($t->resolved_at) / 60 : null;
    }

    /** Did the ticket reach "resolved" within its target time? Null while it is not resolved yet. */
    private function metTarget(Ticket $t): ?bool
    {
        if (! $t->resolved_at || in_array($t->status, ['cancelled'])) {
            return null;
        }

        return $t->resolved_at->lte($t->slaDueAt());
    }

    /** All the headline numbers for a set of tickets (the tickets received in the period). */
    private function stats(Collection $tickets): array
    {
        $cancelled = $tickets->where('status', 'cancelled')->count();
        $counted   = $tickets->count() - $cancelled;
        $done      = $tickets->whereIn('status', ['resolved', 'closed'])->count();
        $hours     = $tickets->map(fn ($t) => in_array($t->status, ['resolved', 'closed']) ? $this->hoursToResolve($t) : null)->filter(fn ($h) => $h !== null);
        $met       = $tickets->map(fn ($t) => in_array($t->status, ['resolved', 'closed']) ? $this->metTarget($t) : null)->filter(fn ($m) => $m !== null);
        $ratings   = $tickets->whereNotNull('rating')->pluck('rating');

        return [
            'total'       => $tickets->count(),
            'cancelled'   => $cancelled,
            'done'        => $done,
            'active'      => $tickets->whereIn('status', Ticket::ACTIVE)->count(),
            'rate'        => $counted > 0 ? (int) round($done / $counted * 100) : null,
            'avgHours'    => $hours->isNotEmpty() ? $hours->avg() : null,
            'metPct'      => $met->isNotEmpty() ? (int) round($met->filter()->count() / $met->count() * 100) : null,
            'rating'      => $ratings->isNotEmpty() ? round($ratings->avg(), 1) : null,
            'ratingCount' => $ratings->count(),
        ];
    }

    /** The same numbers for the period of equal length right before this one (null for "all time"). */
    private function previousStats(?Carbon $from, ?Carbon $to): ?array
    {
        if (! $from || ! $to) {
            return null;
        }

        $days   = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $from->copy()->subDays($days)->startOfDay();

        return $this->stats($this->ticketsIn($prevFrom, $prevTo));
    }

    /**
     * Change versus the previous period. "good" tells the view whether the change is an improvement,
     * because more tickets is neither good nor bad but a slower fix time is bad.
     */
    private function compare(array $now, array $before): array
    {
        $pct = fn ($a, $b) => $b ? (int) round(($a - $b) / $b * 100) : null;

        return [
            'total'    => ['text' => $before['total'] ? $this->signed($pct($now['total'], $before['total'])) . '%' : null, 'good' => null, 'prev' => $before['total']],
            'rate'     => $this->delta($now['rate'], $before['rate'], ' pts', true),
            'avgHours' => $this->delta($now['avgHours'] === null ? null : round($now['avgHours'], 1), $before['avgHours'] === null ? null : round($before['avgHours'], 1), ' h', false),
            'metPct'   => $this->delta($now['metPct'], $before['metPct'], ' pts', true),
            'rating'   => $this->delta($now['rating'], $before['rating'], '', true),
        ];
    }

    private function delta($now, $before, string $unit, bool $higherIsBetter): array
    {
        if ($now === null || $before === null) {
            return ['text' => null, 'good' => null, 'prev' => $before];
        }
        $diff = round($now - $before, 1);

        return [
            'text' => $diff == 0 ? 'No change' : $this->signed($diff) . $unit,
            'good' => $diff == 0 ? null : ($higherIsBetter ? $diff > 0 : $diff < 0),
            'prev' => $before,
        ];
    }

    private function signed($n): string
    {
        return ($n > 0 ? '+' : '') . $n;
    }

    // ---- Charts and tables ---------------------------------------------------

    /** Tickets created and resolved per day (up to ~2 months) or per month. */
    private function trend(?Carbon $from, ?Carbon $to, Collection $received): array
    {
        $start = $from ?? ($received->min('created_at') ? Carbon::parse($received->min('created_at')) : null);
        $end   = $to ?? now();
        if (! $start) {
            return ['points' => [], 'max' => 1, 'unit' => 'month'];
        }

        $daily = $start->diffInDays($end) <= 62;
        $fmt   = $daily ? 'Y-m-d' : 'Y-m';

        $created  = $received->groupBy(fn ($t) => $t->created_at->format($fmt))->map->count();
        $resolved = Ticket::query()->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $start)->where('resolved_at', '<=', $end)
            ->get(['resolved_at'])->groupBy(fn ($t) => $t->resolved_at->format($fmt))->map->count();

        $points = [];
        for ($c = $daily ? $start->copy()->startOfDay() : $start->copy()->startOfMonth(); $c->lte($end); $daily ? $c->addDay() : $c->addMonthNoOverflow()) {
            $k = $c->format($fmt);
            $points[] = [
                'label' => $daily ? $c->format('j') : $c->format('M'),
                'full'  => $daily ? $c->format('D, M j') : $c->format('F Y'),
                'created' => (int) ($created[$k] ?? 0),
                'resolved' => (int) ($resolved[$k] ?? 0),
            ];
        }
        $points = array_slice($points, -24 * ($daily ? 3 : 1));

        return ['points' => $points, 'max' => max(1, collect($points)->flatMap(fn ($p) => [$p['created'], $p['resolved']])->max()), 'unit' => $daily ? 'day' : 'month'];
    }

    /** [{label, value, percent}] in a fixed order (when given) or biggest first. */
    private function breakdown(Collection $counts, ?array $order): array
    {
        $total = max(1, $counts->sum());
        $keys  = $order ? array_values(array_filter($order, fn ($k) => ($counts[$k] ?? 0) > 0)) : $counts->keys()->all();

        return collect($keys)->map(fn ($k) => [
            'key' => $k,
            'label' => ucwords(str_replace('_', ' ', (string) $k)),
            'value' => (int) $counts[$k],
            'percent' => (int) round($counts[$k] / $total * 100),
        ])->values()->all();
    }

    private function engineers(Collection $tickets): array
    {
        $groups = $tickets->whereNotNull('assigned_to')->groupBy('assigned_to');
        $users  = User::whereIn('id', $groups->keys())->get()->keyBy('id');

        return $groups->map(function ($set, $id) use ($users) {
            $s = $this->stats($set);

            return ['user' => $users[$id] ?? null, 'assigned' => $set->count(), 'done' => $s['done'], 'active' => $s['active'],
                    'avgHours' => $s['avgHours'], 'metPct' => $s['metPct'], 'rating' => $s['rating'], 'ratingCount' => $s['ratingCount']];
        })->filter(fn ($r) => $r['user'])->sortByDesc('assigned')->values()->all();
    }

    private function companies(Collection $tickets): array
    {
        return $tickets->groupBy(fn ($t) => $t->user?->company ?: 'No company')
            ->map(fn ($set, $name) => ['name' => $name, 'value' => $set->count()])
            ->sortByDesc('value')->take(6)->values()->all();
    }

    /** What needs attention right now, whatever period is selected. */
    private function snapshot(): array
    {
        $open = Ticket::whereIn('status', ['open', 'assigned', 'in_progress'])->get();

        return [
            'waiting'  => Ticket::awaitingAcceptance()->count(),
            'active'   => Ticket::whereIn('status', Ticket::ACTIVE)->count(),
            'overdue'  => $open->filter->isOverdue()->count(),
            'onHold'   => Ticket::where('status', 'on_hold')->count(),
        ];
    }

    private function accounts(?Carbon $from, ?Carbon $to): array
    {
        return [
            'total'  => User::count(),
            'byRole' => User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role')->all(),
            'added'  => User::when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))->count(),
        ];
    }

    private function summary(string $label, array $s): string
    {
        if ($s['total'] === 0) {
            return "No tickets were submitted in {$label}.";
        }

        $text = "In {$label}, {$s['total']} " . ($s['total'] === 1 ? 'ticket was' : 'tickets were') . ' submitted';
        if ($s['rate'] !== null) {
            $text .= " and {$s['rate']}% " . ($s['rate'] === 100 ? 'were' : 'have been') . ' resolved';
        }
        if ($s['avgHours'] !== null) {
            $h = $s['avgHours'];
            $text .= ', taking ' . ($h < 24 ? round($h, 1) . ' hours' : round($h / 24, 1) . ' days') . ' on average';
        }
        $text .= '.';
        if ($s['metPct'] !== null) {
            $text .= " {$s['metPct']}% of resolved tickets met their target time.";
        }

        return $text;
    }
}
