<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Month calendar of booked (scheduled) tickets for IT Support, Admin and Super Admin.
 * Engineers see only their own assignments; admins see everything and can filter by engineer.
 */
class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $isAdmin = in_array($user->role, ['admin', 'super_admin']);

        $month     = $this->parseMonth($request->query('month'));
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $engineers = $isAdmin ? User::where('role', 'it_support')->orderBy('name')->get(['id', 'name']) : collect();

        $filters = [
            'engineer' => $isAdmin ? (string) $request->query('engineer', '') : '',
            'type'     => in_array($request->query('type'), array_keys(Ticket::SUPPORT_TYPES)) ? $request->query('type') : '',
            'status'   => in_array($request->query('status'), Ticket::STATUSES) ? $request->query('status') : '',
        ];
        if ($filters['engineer'] !== 'unassigned' && ! $engineers->contains('id', (int) $filters['engineer'])) {
            $filters['engineer'] = '';
        }

        $events = Ticket::bookings($user)
            ->with(['user', 'assignee'])
            ->whereBetween('scheduled_for', [$gridStart, $gridEnd])
            ->when($filters['status'] === '', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['type'] !== '', fn ($q) => $q->where('support_type', $filters['type']))
            ->when($filters['engineer'] === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit($filters['engineer']), fn ($q) => $q->where('assigned_to', (int) $filters['engineer']))
            ->orderBy('scheduled_for')->orderBy('id')
            ->get()
            ->groupBy(fn (Ticket $t) => $t->scheduled_for->toDateString());

        $today    = Carbon::today();
        $selected = $this->parseDate($request->query('date'), $gridStart, $gridEnd)
            ?? ($today->between($gridStart, $gridEnd) && $today->isSameMonth($month) ? $today : $month->copy()->startOfMonth());

        $base = fn () => Ticket::bookings($user)->whereIn('status', Ticket::ACTIVE);

        $stats = [
            'today'    => $base()->whereDate('scheduled_for', $today)->count(),
            'week'     => $base()->whereBetween('scheduled_for', [now(), now()->addDays(7)->endOfDay()])->count(),
            'month'    => Ticket::bookings($user)->where('status', '!=', 'cancelled')
                ->whereBetween('scheduled_for', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count(),
            'attention' => $isAdmin
                ? $base()->whereNull('assigned_to')->count()
                : $base()->where('scheduled_for', '<', now())->count(),
        ];

        return view('schedule.index', compact('month', 'gridStart', 'gridEnd', 'events', 'selected', 'today', 'stats', 'filters', 'engineers', 'isAdmin'));
    }

    private function parseMonth(?string $value): Carbon
    {
        if ($value && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value . '-01')->startOfDay();
        }

        return Carbon::today()->startOfMonth();
    }

    private function parseDate(?string $value, Carbon $from, Carbon $to): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->between($from, $to) ? $date : null;
    }
}
