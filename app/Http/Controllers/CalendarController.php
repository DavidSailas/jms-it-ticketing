<?php

namespace App\Http\Controllers;

use App\Models\CalendarNote;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The customer's own calendar: their booked visits and calls, the tickets they logged, and private notes.
 * Tickets are already limited to the signed-in customer by the CompanyScope.
 */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $month     = $this->parseMonth($request->query('month'));
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $bookings = Ticket::query()->with('assignee')
            ->whereNotNull('scheduled_for')->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_for', [$gridStart, $gridEnd])
            ->orderBy('scheduled_for')->orderBy('id')->get()
            ->groupBy(fn (Ticket $t) => $t->scheduled_for->toDateString());

        $logged = Ticket::query()
            ->whereBetween('created_at', [$gridStart, $gridEnd])
            ->orderBy('created_at')->get()
            ->groupBy(fn (Ticket $t) => $t->created_at->toDateString());

        $notes = CalendarNote::query()
            ->where('user_id', $user->id)
            ->whereBetween('note_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->orderBy('id')->get()
            ->groupBy(fn (CalendarNote $n) => $n->note_date->toDateString());

        $today    = Carbon::today();
        $selected = $this->parseDate($request->query('date'), $gridStart, $gridEnd)
            ?? ($today->between($gridStart, $gridEnd) && $today->isSameMonth($month) ? $today : $month->copy()->startOfMonth());

        $next = Ticket::query()->whereIn('status', Ticket::ACTIVE)->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', now())->orderBy('scheduled_for')->first();

        $stats = [
            'week'  => Ticket::query()->whereIn('status', Ticket::ACTIVE)
                ->whereBetween('scheduled_for', [now(), now()->addDays(7)->endOfDay()])->count(),
            'open'  => Ticket::query()->whereIn('status', Ticket::ACTIVE)->count(),
            'notes' => CalendarNote::query()->where('user_id', $user->id)
                ->whereBetween('note_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])->count(),
        ];

        return view('calendar.index', compact('month', 'gridStart', 'gridEnd', 'bookings', 'logged', 'notes', 'selected', 'today', 'next', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'note_date' => ['required', 'date_format:Y-m-d'],
            'body'      => ['required', 'string', 'min:1', 'max:500'],
            'color'     => ['nullable', Rule::in(CalendarNote::COLORS)],
        ], [
            'body.required' => 'Write something for your note.',
            'body.max'      => 'Notes can be up to 500 characters.',
        ]);

        $count = CalendarNote::where('user_id', $request->user()->id)->whereDate('note_date', $data['note_date'])->count();
        if ($count >= CalendarNote::MAX_PER_DAY) {
            return $this->back($data['note_date'])->with('error', 'That day already has ' . CalendarNote::MAX_PER_DAY . ' notes. Delete one to add another.');
        }

        CalendarNote::create([
            'user_id'   => $request->user()->id,
            'note_date' => $data['note_date'],
            'body'      => trim($data['body']),
            'color'     => $data['color'] ?? 'amber',
        ]);

        return $this->back($data['note_date'])->with('success', 'Note added.');
    }

    public function update(Request $request, CalendarNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'body'  => ['required', 'string', 'min:1', 'max:500'],
            'color' => ['nullable', Rule::in(CalendarNote::COLORS)],
        ], ['body.required' => 'Write something for your note.']);

        $note->update(['body' => trim($data['body']), 'color' => $data['color'] ?? $note->color]);

        return $this->back($note->note_date->toDateString())->with('success', 'Note saved.');
    }

    public function destroy(Request $request, CalendarNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $date = $note->note_date->toDateString();
        $note->delete();

        return $this->back($date)->with('success', 'Note deleted.');
    }

    private function back(string $date)
    {
        return redirect()->route('calendar.index', ['month' => substr($date, 0, 7), 'date' => $date]);
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
