<?php

namespace App\Http\Controllers;

use App\Models\CannedReply;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Activity;
use App\Support\Attachments;
use App\Support\Notifier;
use App\Support\TicketProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Flow:  requester submits  ->  admin accepts & assigns an engineer  ->  engineer works it
 *        (on-site or remote)  ->  resolved  ->  requester confirms / rates (closed) or reopens.
 */
class TicketController extends Controller
{
    private const ADMINS = ['admin', 'super_admin'];

    public function index(Request $request)
    {
        $user = $request->user();
        $base = Ticket::query();

        if ($user->role === 'user') {
            $base->where('user_id', $user->id);
        } elseif ($user->role === 'it_support') {
            // Engineers only work on what an admin has assigned to them.
            $base->where('assigned_to', $user->id);
        }

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $needsAssignment = in_array($user->role, self::ADMINS)
            ? (clone $base)->where('status', 'open')->whereNull('assigned_to')->count()
            : 0;

        $status = $request->query('status');

        $sort = in_array($request->query('sort'), ['created_at', 'priority', 'status', 'subject']) ? $request->query('sort') : 'created_at';
        $dir  = $request->query('dir') === 'asc' ? 'asc' : ($request->query('dir') === 'desc' ? 'desc' : ($sort === 'subject' || $sort === 'status' ? 'asc' : 'desc'));
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;

        $tickets = (clone $base)->with(['user', 'assignee'])
            ->when($sort === 'priority', fn ($q) => $q->orderByRaw("CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END {$dir}"))
            ->when($sort === 'status', fn ($q) => $q->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'assigned' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'on_hold' THEN 4 WHEN 'resolved' THEN 5 WHEN 'closed' THEN 6 ELSE 7 END {$dir}"))
            ->when(! in_array($sort, ['priority', 'status']), fn ($q) => $q->orderBy($sort, $dir))
            ->orderByDesc('id')
            ->when($status === 'unassigned', fn ($q) => $q->where('status', 'open')->whereNull('assigned_to'))
            ->when($status && $status !== 'unassigned', fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($x, $s) => $x->where(fn ($y) =>
                $y->where('subject', 'like', "%$s%")
                  ->orWhere('ticket_no', 'like', "%$s%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$s%"))))
            ->paginate($perPage)->onEachSide(1)->withQueryString();

        return view('tickets.index', compact('tickets', 'counts', 'needsAssignment'));
    }

    public function create(Request $request)
    {
        // Partners already see the form on their dashboard; engineers don't log tickets.
        if ($request->user()->role === 'user') {
            return redirect()->route('dashboard');
        }
        abort_unless($request->user()->canLogTickets(), 403);

        return view('tickets.create');
    }

    public function store(Request $request)
    {
        // Tickets are logged by the people of a partner company; JMS's own people (super admins, JMS admins) belong to none.
        abort_unless($request->user()->canLogTickets(), 403);

        $data = $request->validate([
            'subject'       => 'required|string|min:5|max:150',
            'category'      => ['required', Rule::in(Ticket::CATEGORIES)],
            'priority'      => ['required', Rule::in(Ticket::PRIORITIES)],
            'description'   => 'required|string|min:10|max:5000',
            'when'          => ['required', Rule::in(['now', 'later'])],
            'scheduled_for' => $this->scheduleRules(),
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'location'      => 'nullable|string|max:255',
        ] + Attachments::rules(), Attachments::messages() + [
            'subject.required'     => 'Enter a short subject for your ticket.',
            'subject.min'          => 'The subject must be at least 5 characters.',
            'subject.max'          => 'The subject can be at most 150 characters.',
            'category.required'    => 'Choose a category.',
            'category.in'          => 'Choose one of the listed categories.',
            'priority.required'    => 'Choose a priority.',
            'priority.in'          => 'Choose one of the listed priorities.',
            'description.required' => 'Describe the problem so our team can help.',
            'description.min'      => 'Please add a little more detail (at least 10 characters).',
            'description.max'      => 'The description can be at most 5000 characters.',
            'when.required'        => 'Choose when you need help.',
            'scheduled_for.required_if' => 'Pick the date and time you want us to come or call.',
            'scheduled_for.date'   => 'Enter a valid date and time.',
            'contact_phone.regex'  => 'Enter a valid phone number (digits, spaces, + ( ) and - only).',
            'contact_phone.max'    => 'The phone number is too long.',
            'location.max'         => 'The location can be at most 255 characters.',
        ]);

        // The files are not a column on the ticket; they are saved on their own below.
        unset($data['files']);
        $data = $this->applySchedule($data);

        $ticket = $request->user()->tickets()->create($data);
        $saved  = Attachments::store($ticket, $request->user(), $request->file('files', []));

        Notifier::ticketCreated($ticket, $request->user());
        Activity::record($request->user(), 'ticket_created', "Submitted {$ticket->ticket_no}: {$ticket->subject}" . Attachments::note($saved), $ticket);

        return redirect()->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_no} submitted. Our IT team will respond soon.");
    }

    public function show(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        $this->authorizeView($user, $ticket);

        $ticket->load(['user', 'assignee', 'acceptor', 'attachments']);

        $comments = $ticket->comments()->with(['user', 'attachments'])
            ->when($user->role === 'user', fn ($q) => $q->where('is_internal', false))
            ->oldest()->get();

        $timeline = $ticket->activity()->with('user')
            ->when($user->role === 'user', fn ($q) => $q->where('action', '!=', 'note_added'))
            ->oldest('created_at')->get();

        $isAdmin   = in_array($user->role, self::ADMINS);
        $engineers = $isAdmin ? $ticket->assignableEngineers($user) : collect();
        $workload  = $isAdmin
            ? Ticket::whereIn('status', ['assigned', 'in_progress', 'on_hold'])->whereNotNull('assigned_to')
                ->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to')
            : collect();

        // Saved replies (staff only), with this ticket's details already filled in.
        $cannedReplies = $user->isStaff()
            ? CannedReply::visibleTo($user)->orderBy('title')->get()
                ->map(fn ($r) => ['id' => $r->id, 'title' => $r->title, 'text' => $r->renderFor($ticket, $user)])->values()
            : collect();

        return view('tickets.show', compact('ticket', 'comments', 'timeline', 'engineers', 'workload', 'cannedReplies'));
    }

    /** Admin / Super Admin: edit what the requester submitted (details and schedule). */
    public function edit(Ticket $ticket)
    {
        if (in_array($ticket->status, ['cancelled', 'closed'])) {
            return redirect()->route('tickets.show', $ticket)->with('error', 'A ' . strtolower($ticket->statusLabel()) . ' ticket can no longer be edited.');
        }

        return view('tickets.edit', compact('ticket'));
    }

    public function updateDetails(Request $request, Ticket $ticket)
    {
        if (in_array($ticket->status, ['cancelled', 'closed'])) {
            return redirect()->route('tickets.show', $ticket)->with('error', 'A ' . strtolower($ticket->statusLabel()) . ' ticket can no longer be edited.');
        }

        $data = $request->validate([
            'subject'       => 'required|string|min:5|max:150',
            'category'      => ['required', Rule::in(Ticket::CATEGORIES)],
            'priority'      => ['required', Rule::in(Ticket::PRIORITIES)],
            'description'   => 'required|string|min:10|max:5000',
            'when'          => ['required', Rule::in(['now', 'later'])],
            'scheduled_for' => $this->scheduleRules($ticket),
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'location'      => 'nullable|string|max:255',
        ], [
            'subject.min'          => 'The subject must be at least 5 characters.',
            'description.min'      => 'Please add a little more detail (at least 10 characters).',
            'scheduled_for.required_if' => 'Pick the date and time for the scheduled visit or call.',
            'scheduled_for.date'   => 'Enter a valid date and time.',
            'contact_phone.regex'  => 'Enter a valid phone number (digits, spaces, + ( ) and - only).',
        ]);

        $actor = $request->user();
        $old = $ticket->only(['status', 'priority', 'assigned_to']);

        $ticket->update($this->applySchedule($data));

        $labels = ['subject' => 'subject', 'category' => 'category', 'priority' => 'priority', 'description' => 'description',
                   'contact_phone' => 'contact number', 'location' => 'location', 'scheduled_for' => 'schedule'];
        $changed = collect(array_keys($ticket->getChanges()))->intersect(array_keys($labels))->map(fn ($k) => $labels[$k])->values();

        if ($changed->isEmpty()) {
            return redirect()->route('tickets.show', $ticket)->with('success', 'No changes were made.');
        }

        $ticket = $ticket->fresh();
        Notifier::ticketUpdated($ticket, $actor, $old);
        if ($changed->contains('schedule')) {
            Notifier::ticketRescheduled($ticket, $actor);
        }
        Activity::record($actor, 'ticket_updated', "Edited {$ticket->ticket_no}: " . $changed->implode(', '), $ticket);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    /** Admin: free-form override (status / priority / engineer). The "Accept & assign" form is the normal way. */
    public function update(Request $request, Ticket $ticket)
    {
        if ($ticket->isLockedToJms($request->user())) {
            return back()->with('error', 'JMS support is handling this ticket, so only JMS can reassign it. Add a reply on the ticket if you need to tell them something.');
        }

        $data = $request->validate([
            'status'      => ['required', Rule::in(Ticket::STATUSES)],
            'priority'    => ['required', Rule::in(Ticket::PRIORITIES)],
            'assigned_to' => ['nullable', Rule::in($ticket->assignableEngineers($request->user())->pluck('id')->all())],
        ]);

        // Keep status and assignment consistent.
        if (! empty($data['assigned_to'])) {
            if ($data['status'] === 'open') $data['status'] = 'assigned';
            $data['accepted_by'] = $ticket->accepted_by ?? $request->user()->id;
            $data['accepted_at'] = $ticket->accepted_at ?? now();
        } elseif ($data['status'] === 'assigned') {
            $data['status'] = 'open';
        }

        $data['resolved_at'] = in_array($data['status'], ['resolved', 'closed']) ? ($ticket->resolved_at ?? now()) : null;
        $old = $ticket->only(['status', 'priority', 'assigned_to']);
        $ticket->update($data);
        $ticket = $ticket->fresh();
        Notifier::ticketUpdated($ticket, $request->user(), $old);

        $changes = [];
        if ($ticket->status !== $old['status'])     $changes[] = 'status to ' . $ticket->statusLabel();
        if ($ticket->priority !== $old['priority']) $changes[] = 'priority to ' . $ticket->priorityLabel();
        if ((int) $ticket->assigned_to !== (int) $old['assigned_to']) {
            $changes[] = $ticket->assigned_to ? 'engineer to ' . $ticket->assignee?->name : 'engineer to Unassigned';
        }
        if ($changes) {
            Activity::record($request->user(), 'ticket_updated', "Changed {$ticket->ticket_no}: " . implode(', ', $changes), $ticket);
        }

        return back()->with('success', 'Ticket updated.');
    }

    /** Admin: accept the ticket and hand it to an IT engineer (on-site or remote). */
    public function assign(Request $request, Ticket $ticket)
    {
        if ($ticket->isLockedToJms($request->user())) {
            return back()->with('error', 'JMS support is handling this ticket, so only JMS can reassign it. Add a reply on the ticket if you need to tell them something.');
        }

        if ($ticket->isFinished()) {
            return back()->with('error', 'This ticket is already ' . strtolower($ticket->statusLabel()) . ' and can no longer be assigned.');
        }

        $data = $request->validate([
            'assigned_to'  => ['required', Rule::in($ticket->assignableEngineers($request->user())->pluck('id')->all())],
            'support_type' => ['required', Rule::in(array_keys(Ticket::SUPPORT_TYPES))],
            'priority'     => ['required', Rule::in(Ticket::PRIORITIES)],
            'note'         => 'nullable|string|max:500',
        ], [
            'assigned_to.required' => 'Choose the engineer who will handle this ticket.',
            'assigned_to.in'       => 'Choose an IT Support engineer from the list.',
            'support_type.required' => 'Choose on-site or remote support.',
        ]);

        $actor = $request->user();
        $old = $ticket->only(['status', 'priority', 'assigned_to']);

        $ticket->update([
            'assigned_to'  => $data['assigned_to'],
            'support_type' => $data['support_type'],
            'priority'     => $data['priority'],
            'status'       => in_array($ticket->status, ['open', 'assigned']) ? 'assigned' : $ticket->status,
            'accepted_by'  => $ticket->accepted_by ?? $actor->id,
            'accepted_at'  => $ticket->accepted_at ?? now(),
        ]);

        if (! empty($data['note'])) {
            $ticket->comments()->create(['user_id' => $actor->id, 'body' => 'Dispatch note: ' . $data['note'], 'is_internal' => true]);
        }

        $ticket = $ticket->fresh(['assignee']);
        Notifier::ticketUpdated($ticket, $actor, $old);
        Activity::record($actor, 'ticket_assigned',
            "Accepted {$ticket->ticket_no} and assigned it to {$ticket->assignee->name} ({$ticket->supportTypeLabel()})", $ticket);

        return back()->with('success', "Ticket accepted and assigned to {$ticket->assignee->name}.");
    }

    /** Engineer: start, pause or finish the work. */
    public function progress(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        $this->authorizeView($user, $ticket);

        if ($ticket->isFinished()) {
            return back()->with('error', 'This ticket is already ' . strtolower($ticket->statusLabel()) . '.');
        }
        if (! $ticket->assigned_to) {
            return back()->with('error', 'Assign an engineer before updating the progress.');
        }

        $data = $request->validate([
            'status'     => ['required', Rule::in(['in_progress', 'on_hold', 'resolved'])],
            'resolution' => ['required_if:status,resolved', 'nullable', 'string', 'min:10', 'max:3000'],
        ], [
            'resolution.required_if' => 'Describe what you did to fix it, so the requester knows.',
            'resolution.min'         => 'Please add a little more detail (at least 10 characters).',
        ]);

        $resolved = $data['status'] === 'resolved';
        TicketProgress::apply($user, $ticket, $data['status'], $data['resolution'] ?? null);

        return back()->with('success', $resolved ? 'Ticket marked as resolved.' : 'Status updated.');
    }

    /** IT Support / admin decide how the ticket is handled: remote or on-site. */
    public function supportType(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        $this->authorizeView($user, $ticket);

        if ($ticket->isFinished()) {
            return back()->with('error', 'This ticket is already ' . strtolower($ticket->statusLabel()) . '.');
        }

        $data = $request->validate(['support_type' => ['required', Rule::in(array_keys(Ticket::SUPPORT_TYPES))]]);

        if ($ticket->support_type !== $data['support_type']) {
            $ticket->update(['support_type' => $data['support_type']]);
            $ticket = $ticket->fresh();

            Notifier::supportTypeSet($ticket, $user);
            Activity::record($user, 'ticket_updated', "Set {$ticket->ticket_no} to {$ticket->supportTypeLabel()}", $ticket);
        }

        return back()->with('success', "Marked as {$ticket->supportTypeLabel()}.");
    }

    /** Requester: cancel while nobody has accepted the ticket yet. */
    public function cancel(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        abort_unless($ticket->user_id === $user->id, 403);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        // Re-check at the moment of cancelling: an admin may have accepted it in the meantime.
        if (! $ticket->isCancellable()) {
            return back()->with('error', "{$ticket->ticket_no} can no longer be cancelled because our team has already accepted it. Please reply on the ticket instead.");
        }

        $ticket->update(['status' => 'cancelled', 'resolved_at' => null]);

        if (! empty($data['reason'])) {
            $ticket->comments()->create(['user_id' => $user->id, 'body' => 'Cancelled by requester: ' . $data['reason'], 'is_internal' => false]);
        }

        Notifier::ticketCancelled($ticket, $user);
        Activity::record($user, 'ticket_cancelled', "Cancelled {$ticket->ticket_no}: {$ticket->subject}", $ticket);

        return redirect()->route('tickets.index')->with('success', "Ticket {$ticket->ticket_no} was cancelled.");
    }

    /** Requester: "yes, it's fixed" - rate the support and close the ticket. */
    public function feedback(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        abort_unless($ticket->user_id === $user->id, 403);

        if ($ticket->status !== 'resolved') {
            return back()->with('error', 'Only a resolved ticket can be confirmed.');
        }

        $data = $request->validate([
            'rating'  => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500',
        ], ['rating.required' => 'Pick a rating from 1 to 5.', 'rating.between' => 'Pick a rating from 1 to 5.']);

        $ticket->update(['rating' => $data['rating'], 'rating_comment' => $data['comment'] ?? null, 'status' => 'closed']);

        Notifier::ticketFeedback($ticket->fresh(), $user);
        Activity::record($user, 'ticket_closed', "Confirmed {$ticket->ticket_no} as fixed and rated it {$data['rating']}/5", $ticket);

        return back()->with('success', 'Thank you! The ticket is now closed.');
    }

    /** Requester: "it's not fixed" - send it back to the engineer. */
    public function reopen(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        abort_unless($ticket->user_id === $user->id, 403);

        if ($ticket->status !== 'resolved') {
            return back()->with('error', 'Only a resolved ticket can be reopened.');
        }

        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => 'Tell us what is still not working.',
            'reason.min'      => 'Please add a little more detail (at least 5 characters).',
        ]);

        $ticket->update(['status' => $ticket->assigned_to ? 'assigned' : 'open', 'resolved_at' => null]);
        $ticket->comments()->create(['user_id' => $user->id, 'body' => 'Reopened by requester: ' . $data['reason'], 'is_internal' => false]);

        Notifier::ticketReopened($ticket->fresh(), $user);
        Activity::record($user, 'ticket_reopened', "Reopened {$ticket->ticket_no}", $ticket);

        return back()->with('success', 'Ticket reopened. Our team has been notified.');
    }

    public function comment(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        $this->authorizeView($user, $ticket);

        if ($ticket->status === 'cancelled') {
            return back()->with('error', 'This ticket was cancelled, so replies are turned off.');
        }

        $request->validate(
            ['body' => ['required_without:files', 'nullable', 'string', 'max:3000']] + Attachments::rules(),
            Attachments::messages() + ['body.required_without' => 'Write a reply or attach a file.']
        );

        $files = $request->file('files', []);
        $body  = trim((string) $request->body);

        $comment = $ticket->comments()->create([
            'user_id'     => $user->id,
            // A reply that is only a screenshot or log file still needs some text to show in the conversation.
            'body'        => $body !== '' ? $body : (count($files) === 1 ? 'Attached a file.' : 'Attached ' . count($files) . ' files.'),
            'is_internal' => $user->isStaff() && $request->boolean('is_internal'),
        ]);
        $saved = Attachments::store($ticket, $user, $files, $comment);

        Notifier::commentAdded($ticket, $comment, $user);
        Activity::record(
            $user,
            $comment->is_internal ? 'note_added' : 'comment_added',
            ($comment->is_internal ? 'Added an internal note on ' : 'Replied on ') . $ticket->ticket_no . Attachments::note($saved),
            $ticket
        );

        return back()->with('success', 'Reply added.');
    }

    /** The "when" choice plus the date/time it needs. */
    private function scheduleRules(?Ticket $existing = null): array
    {
        return [
            'required_if:when,later', 'nullable', 'date',
            function ($attribute, $value, $fail) use ($existing) {
                if (! $value) return;

                $at = Carbon::parse($value);
                // Keep an unchanged date even if it has since passed.
                if ($existing?->scheduled_for && $existing->scheduled_for->format('Y-m-d H:i') === $at->format('Y-m-d H:i')) return;

                if ($at->lte(now())) $fail('Pick a date and time in the future.');
                elseif ($at->gt(now()->addDays(90))) $fail('You can schedule at most 90 days ahead.');
            },
        ];
    }

    /** "now" clears the schedule; "later" stores the chosen moment. */
    private function applySchedule(array $data): array
    {
        $data['scheduled_for'] = ($data['when'] ?? 'now') === 'later' && ! empty($data['scheduled_for'])
            ? Carbon::parse($data['scheduled_for'])
            : null;
        unset($data['when']);

        return $data;
    }

    private function authorizeView(User $user, Ticket $ticket): void
    {
        abort_if($user->role === 'user' && (int) $ticket->user_id !== (int) $user->id, 403);
        abort_if($user->role === 'it_support' && (int) $ticket->assigned_to !== (int) $user->id, 403);
    }
}
