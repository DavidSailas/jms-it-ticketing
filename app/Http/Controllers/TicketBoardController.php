<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketProgress;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kanban view of the tickets staff work on. Cards move between the work columns by drag and drop (or the Move menu). */
class TicketBoardController extends Controller
{
    private const COLUMNS = [
        'open'        => 'Open',
        'assigned'    => 'Assigned',
        'in_progress' => 'In progress',
        'on_hold'     => 'On hold',
        'resolved'    => 'Resolved',
    ];

    /** Resolved tickets stay on the board this many days, then drop off (they remain in the list). */
    private const RESOLVED_DAYS = 14;
    private const PER_COLUMN    = 60;

    public function index(Request $request)
    {
        $user = $request->user();

        $columns = self::COLUMNS;
        $cards   = $this->allCards($user);

        return view('tickets.board', [
            'columns' => $columns,
            'cards'   => $cards,
            'config'  => ['moveUrl' => url('tickets/board'), 'cardsUrl' => route('tickets.board.cards'), 'columns' => $columns, 'resolvedDays' => self::RESOLVED_DAYS],
        ]);
    }

    /** The whole board as JSON: the page calls this when a live update says something changed. */
    public function cards(Request $request)
    {
        return response()->json(['cards' => $this->allCards($request->user())]);
    }

    public function move(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        abort_unless($this->visible($user)->whereKey($ticket->id)->exists(), 403);

        if ($ticket->isFinished()) {
            return response()->json(['message' => 'This ticket is already ' . strtolower($ticket->statusLabel()) . '.'], 422);
        }
        if (! $ticket->assigned_to) {
            return response()->json(['message' => 'Assign an engineer first: open the ticket and use "Accept & assign".'], 422);
        }

        $data = $request->validate([
            'status'     => ['required', Rule::in(TicketProgress::TARGETS)],
            'resolution' => ['required_if:status,resolved', 'nullable', 'string', 'min:10', 'max:3000'],
        ], [
            'resolution.required_if' => 'Describe what you did to fix it, so the requester knows.',
            'resolution.min'         => 'Please add a little more detail (at least 10 characters).',
        ]);

        $ticket = TicketProgress::apply($user, $ticket, $data['status'], $data['resolution'] ?? null);

        return response()->json(['card' => $this->card($ticket->load(['user', 'assignee', 'company']))]);
    }

    /** The tickets this person may see on the board: the same people as the list (engineers only their own). */
    private function visible(User $user)
    {
        return Ticket::query()->when($user->role === 'it_support', fn ($q) => $q->where('assigned_to', $user->id));
    }

    private function allCards(User $user): array
    {
        $cards = [];

        foreach (array_keys(self::COLUMNS) as $status) {
            $rows = $this->visible($user)->where('status', $status)
                ->when($status === 'resolved', fn ($q) => $q->where('resolved_at', '>=', now()->subDays(self::RESOLVED_DAYS)))
                ->with(['user', 'assignee', 'company'])
                ->orderByRaw("CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
                ->oldest()->limit(self::PER_COLUMN)->get();

            foreach ($rows as $t) {
                $cards[] = $this->card($t);
            }
        }

        return $cards;
    }

    private function card(Ticket $t): array
    {
        return [
            'id'        => $t->id,
            'no'        => $t->ticket_no,
            'subject'   => $t->subject,
            'status'    => $t->status,
            'priority'  => $t->priority,
            'company'   => $t->company?->name,
            'requester' => $t->user?->name,
            'assignee'  => $t->assignee?->name,
            'url'       => route('tickets.show', $t),
            'due'       => $t->slaClock()['due'] ?? null,
            // Only tickets that have an engineer and are not finished can be dragged.
            'movable'   => $t->assigned_to !== null && ! $t->isFinished(),
        ];
    }
}
