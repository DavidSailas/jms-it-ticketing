<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Ticket;

/**
 * Everything the admin dashboard charts need, in one place, so the first page load and the live refresh
 * (DashboardChartController) always show the same numbers. Ticket queries go through the CompanyScope, so a
 * partner's admin only ever gets their own company's figures; JMS super admins and JMS admins get everything.
 */
class DashboardCharts
{
    /** Range key => [days covered, days per point]. */
    public const RANGES = ['7d' => [7, 1], '30d' => [30, 1], '12w' => [84, 7]];

    /** Same colours as the status badges elsewhere in the app. */
    public const STATUS_COLORS = [
        'open' => '#3b82f6', 'assigned' => '#6366f1', 'in_progress' => '#f59e0b', 'on_hold' => '#94a3b8',
        'resolved' => '#10b981', 'closed' => '#cbd5e1', 'cancelled' => '#fb7185',
    ];

    public static function range(?string $key): string
    {
        return array_key_exists((string) $key, self::RANGES) ? (string) $key : '7d';
    }

    /** @return array<string,mixed> */
    public static function payload(string $range = '7d', ?array $kpis = null): array
    {
        return [
            'range'      => $range,
            'kpis'       => $kpis ?? self::kpis(),
            'trend'      => self::trend($range),
            'status'     => self::status(),
            'categories' => self::categories(),
        ];
    }

    /** The six numbers on the dashboard cards. */
    public static function kpis(?int $overdue = null): array
    {
        $active = Ticket::ACTIVE;

        return [
            'unassigned'    => Ticket::where('status', 'open')->whereNull('assigned_to')->count(),
            'assigned'      => Ticket::where('status', 'assigned')->count(),
            'in_progress'   => Ticket::where('status', 'in_progress')->count(),
            'urgent'        => Ticket::whereIn('status', $active)->whereIn('priority', ['high', 'critical'])->count(),
            'overdue'       => $overdue ?? Ticket::whereIn('status', ['open', 'assigned', 'in_progress'])->get()->filter->isOverdue()->count(),
            'resolved_week' => Ticket::whereIn('status', ['resolved', 'closed'])->where('resolved_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * New and resolved tickets per day (or per week for the 12 week view).
     *
     * @return array{labels:string[],full:string[],created:int[],resolved:int[],totals:array{created:int,resolved:int}}
     */
    public static function trend(string $range = '7d'): array
    {
        [$days, $step] = self::RANGES[self::range($range)];

        $start  = now()->subDays($days - 1)->startOfDay();
        $points = (int) ceil($days / $step);

        $labels = [];
        $full = [];
        $bucketOf = [];   // 'Y-m-d' => index of the point that day belongs to

        for ($d = 0; $d < $days; $d++) {
            $day = $start->copy()->addDays($d);
            $bucketOf[$day->format('Y-m-d')] = intdiv($d, $step);

            if ($d % $step === 0) {
                if ($step === 1) {
                    $labels[] = $days <= 7 ? $day->format('D') : $day->format('M j');
                    $full[]   = $day->format('l, M j');
                } else {
                    $end      = $start->copy()->addDays(min($d + $step, $days) - 1);
                    $labels[] = $day->format('M j');
                    $full[]   = $day->format('M j') . ' - ' . $end->format('M j');
                }
            }
        }

        $created  = array_fill(0, $points, 0);
        $resolved = array_fill(0, $points, 0);

        Ticket::where('created_at', '>=', $start)->get(['created_at'])->each(function ($t) use (&$created, $bucketOf) {
            $i = $bucketOf[$t->created_at->format('Y-m-d')] ?? null;
            if ($i !== null) {
                $created[$i]++;
            }
        });

        Ticket::where('resolved_at', '>=', $start)->get(['resolved_at'])->each(function ($t) use (&$resolved, $bucketOf) {
            $i = $bucketOf[$t->resolved_at->format('Y-m-d')] ?? null;
            if ($i !== null) {
                $resolved[$i]++;
            }
        });

        return [
            'labels'   => $labels,
            'full'     => $full,
            'created'  => $created,
            'resolved' => $resolved,
            'totals'   => ['created' => array_sum($created), 'resolved' => array_sum($resolved)],
        ];
    }

    /** One row per status (zeros included, so the legend never jumps around). */
    public static function status(): array
    {
        $counts = Ticket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(Ticket::STATUSES)->map(fn (string $s) => [
            'key'   => $s,
            'label' => ucwords(str_replace('_', ' ', $s)),
            'value' => (int) ($counts[$s] ?? 0),
            'color' => self::STATUS_COLORS[$s] ?? '#94a3b8',
            'url'   => route('tickets.index', ['status' => $s]),
        ])->all();
    }

    /** The categories people ask for help with most. */
    public static function categories(int $limit = 6): array
    {
        return Ticket::selectRaw('category, count(*) as total')->groupBy('category')
            ->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($r) => ['label' => (string) $r->category, 'value' => (int) $r->total])->all();
    }

    /**
     * One row per partner company for the super admin: who needs a decision first.
     * JMS's own record (if any) is left out; JMS is not a partner.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function partners(): array
    {
        $jms = Company::jmsIds();
        $companies = Company::query()->when($jms, fn ($q) => $q->whereNotIn('id', $jms))->orderBy('name')->get();

        if ($companies->isEmpty()) {
            return [];
        }

        $active = Ticket::whereIn('status', Ticket::ACTIVE)
            ->get(['id', 'company_id', 'status', 'priority', 'assigned_to', 'created_at', 'scheduled_for'])
            ->groupBy('company_id');

        $resolved = Ticket::whereIn('status', ['resolved', 'closed'])->where('resolved_at', '>=', now()->subDays(30))
            ->selectRaw('company_id, count(*) as total')->groupBy('company_id')->pluck('total', 'company_id');

        $ratings = Ticket::whereNotNull('rating')
            ->selectRaw('company_id, avg(rating) as avg_rating')->groupBy('company_id')->pluck('avg_rating', 'company_id');

        return $companies->map(function (Company $c) use ($active, $resolved, $ratings) {
            $set = $active->get($c->id, collect());

            $row = [
                'company'  => $c,
                'active'   => $set->count(),
                'awaiting' => $set->where('status', 'open')->whereNull('assigned_to')->count(),
                'overdue'  => $set->filter->isOverdue()->count(),
                'urgent'   => $set->whereIn('priority', ['high', 'critical'])->count(),
                'resolved' => (int) ($resolved[$c->id] ?? 0),
                'rating'   => isset($ratings[$c->id]) ? round((float) $ratings[$c->id], 1) : null,
            ];
            $row['state'] = $row['overdue'] > 0 ? 'attention' : (($row['urgent'] > 0 || $row['awaiting'] > 0) ? 'watch' : 'ok');

            return $row;
        })->sortBy([
            fn ($a, $b) => $b['overdue'] <=> $a['overdue'],
            fn ($a, $b) => $b['urgent'] <=> $a['urgent'],
            fn ($a, $b) => $b['active'] <=> $a['active'],
            fn ($a, $b) => strcasecmp($a['company']->name, $b['company']->name),
        ])->values()->all();
    }
}
