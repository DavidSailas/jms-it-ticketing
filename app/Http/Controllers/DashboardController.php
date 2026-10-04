<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const ACTIVE = ['open', 'assigned', 'in_progress', 'on_hold'];
    private const DONE = ['resolved', 'closed'];

    /** Rows per page in the Super Admin "Recent system activity" list. */
    private const ACTIVITY_PER_PAGE = 15;

    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Partner users land straight on the "submit a ticket" form.
        if ($user->role === 'user') {
            $mine = fn () => Ticket::where('user_id', $user->id);

            return view('dashboard-user', [
                'counts' => [
                    'open'        => $mine()->where('status', 'open')->count(),
                    'in_progress' => $mine()->whereIn('status', ['assigned', 'in_progress', 'on_hold'])->count(),
                    'resolved'    => $mine()->whereIn('status', self::DONE)->count(),
                ],
                'recent' => $mine()->latest()->take(5)->get(),
            ]);
        }

        // IT engineers: their own assigned work, with maps and contact shortcuts.
        if ($user->role === 'it_support') {
            $mine = fn () => Ticket::where('assigned_to', $user->id);

            return view('dashboard-engineer', [
                'stats' => [
                    'todo'          => $mine()->where('status', 'assigned')->count(),
                    'in_progress'   => $mine()->where('status', 'in_progress')->count(),
                    'on_hold'       => $mine()->where('status', 'on_hold')->count(),
                    'resolved_week' => $mine()->whereIn('status', self::DONE)->where('resolved_at', '>=', now()->subDays(7))->count(),
                ],
                'queue' => $mine()->with('user')->whereIn('status', ['assigned', 'in_progress', 'on_hold'])
                    ->orderByRaw('CASE WHEN assigned_to IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                    ->orderBy('created_at')->get(),
            ]);
        }

        // Admin / Super Admin: triage and monitoring dashboard.
        // Tickets whose service-level target has already passed, worst first.
        $overdueTickets = Ticket::with(['user', 'assignee'])->whereIn('status', ['open', 'assigned', 'in_progress'])->get()
            ->filter->isOverdue()
            ->sortBy(fn (Ticket $t) => $t->slaDueAt()->timestamp)
            ->values();

        // Next bookings (today's remaining ones and everything after), soonest first.
        $upcoming = Ticket::with(['user', 'assignee'])->whereNotNull('scheduled_for')
            ->whereIn('status', self::ACTIVE)->where('scheduled_for', '>=', now()->startOfDay())
            ->orderBy('scheduled_for')->orderBy('id')->take(5)->get();

        $stats = [
            'unassigned'    => Ticket::where('status', 'open')->whereNull('assigned_to')->count(),
            'assigned'      => Ticket::where('status', 'assigned')->count(),
            'in_progress'   => Ticket::where('status', 'in_progress')->count(),
            'urgent'        => Ticket::whereIn('status', self::ACTIVE)->whereIn('priority', ['high', 'critical'])->count(),
            'overdue'       => $overdueTickets->count(),
            'resolved_week' => Ticket::whereIn('status', self::DONE)->where('resolved_at', '>=', now()->subDays(7))->count(),
        ];

        $byStatus = Ticket::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byCategory = Ticket::selectRaw('category, count(*) as total')->groupBy('category')->orderByDesc('total')->take(5)->pluck('total', 'category');

        $priorityOrder = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

        // "Waiting for acceptance" is rendered live from the shared pending feed (see layouts/app).

        // Already with an engineer.
        $queue = Ticket::with(['user', 'assignee'])->whereIn('status', ['assigned', 'in_progress', 'on_hold'])
            ->orderByRaw($priorityOrder)->orderBy('created_at')->take(8)->get();

        // Engineer workload.
        $load = Ticket::whereIn('status', ['assigned', 'in_progress', 'on_hold'])->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $engineers = User::where('role', 'it_support')->orderBy('name')->get()
            ->each(fn ($e) => $e->setAttribute('active_tickets', (int) ($load[$e->id] ?? 0)));

        // Last 7 days: created vs resolved.
        $from = now()->subDays(6)->startOfDay();
        $created  = Ticket::where('created_at', '>=', $from)->get(['created_at'])->groupBy(fn ($t) => $t->created_at->format('Y-m-d'));
        $resolved = Ticket::where('resolved_at', '>=', $from)->get(['resolved_at'])->groupBy(fn ($t) => $t->resolved_at->format('Y-m-d'));
        $trend = collect(range(6, 0))->map(function ($i) use ($created, $resolved) {
            $d = now()->subDays($i);

            return [
                'label'    => $d->format('D'),
                'created'  => $created->get($d->format('Y-m-d'), collect())->count(),
                'resolved' => $resolved->get($d->format('Y-m-d'), collect())->count(),
            ];
        });

        // Service quality.
        $finished = Ticket::whereNotNull('resolved_at')->get(['created_at', 'resolved_at', 'priority']);
        $quality = [
            'avg_minutes' => $finished->isEmpty() ? null : $finished->avg(fn ($t) => abs($t->created_at->diffInMinutes($t->resolved_at))),
            'sla_rate'    => $finished->isEmpty() ? null : round($finished->filter(fn ($t) => $t->resolved_at->lte($t->slaDueAt()))->count() / $finished->count() * 100),
            'rating'      => Ticket::whereNotNull('rating')->avg('rating'),
            'rated'       => Ticket::whereNotNull('rating')->count(),
        ];

        // Super Admin only: accounts, sign-in security and a system-wide activity feed.
        $system = null;
        if ($user->role === 'super_admin') {
            $roleCounts = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

            $system = [
                'roles'    => $roleCounts,
                'accounts' => (int) $roleCounts->sum(),
                'logins'   => ActivityLog::where('action', 'login')->where('created_at', '>=', now()->subDay())->count(),
                'failed'   => ActivityLog::where('action', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
                'security' => ActivityLog::where('category', 'security')->where('created_at', '>=', now()->subDays(7))->count(),
                'recent'   => ActivityLog::with('user')->latest('created_at')->latest('id')
                    ->paginate(self::ACTIVITY_PER_PAGE, ['*'], 'activity_page')
                    ->fragment('system-activity')->withQueryString(),
            ];
        }

        return view('dashboard', compact('stats', 'byStatus', 'byCategory', 'queue', 'engineers', 'trend', 'quality', 'system', 'overdueTickets', 'upcoming'));
    }
}
