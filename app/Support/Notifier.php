<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Decides WHO is told about WHAT. Controllers just call one method.
 * The person who did the action is never notified about their own action.
 */
class Notifier
{
    public static function ticketCreated(Ticket $ticket, User $actor): void
    {
        $urgent = in_array($ticket->priority, ['high', 'critical']);

        self::send(self::admins($ticket), $actor, [
            'kind'    => $ticket->priority === 'critical' ? 'urgent' : 'new',
            'title'   => ($ticket->priority === 'critical' ? 'Critical ticket ' : 'New ticket ') . $ticket->ticket_no,
            'message' => "{$actor->name}" . ($actor->company ? " ({$actor->company})" : '') . ': ' . $ticket->subject
                . ($urgent && $ticket->priority !== 'critical' ? ' - High priority' : ''),
        ], $ticket);
    }

    public static function ticketCancelled(Ticket $ticket, User $actor): void
    {
        self::send(self::admins($ticket), $actor, [
            'kind'    => 'status',
            'title'   => "Ticket cancelled {$ticket->ticket_no}",
            'message' => "{$actor->name} cancelled: {$ticket->subject}",
        ], $ticket);
    }

    /** $old = status / priority / assigned_to before the update. */
    public static function ticketUpdated(Ticket $ticket, User $actor, array $old): void
    {
        $ticket->loadMissing('user', 'assignee');
        $newlyAssigned = $ticket->assigned_to && (int) $ticket->assigned_to !== (int) $old['assigned_to'];

        if ($newlyAssigned) {
            self::send(collect([$ticket->assignee]), $actor, [
                'kind'    => 'assigned',
                'title'   => 'Ticket assigned to you',
                'message' => "{$ticket->ticket_no}: {$ticket->subject}",
            ], $ticket);

            self::send(collect([$ticket->user]), $actor, [
                'kind'    => 'assigned',
                'title'   => 'An engineer was assigned',
                'message' => "{$ticket->assignee->name} is now handling {$ticket->ticket_no}.",
            ], $ticket);
        }

        // A fresh assignment already told the requester; don't repeat it as a status change.
        if ($ticket->status !== $old['status'] && ! ($newlyAssigned && $ticket->status === 'assigned')) {
            $done = in_array($ticket->status, ['resolved', 'closed']);

            self::send(collect([$ticket->user]), $actor, [
                'kind'    => $done ? 'resolved' : 'status',
                'title'   => $ticket->status === 'resolved' ? 'Your ticket was resolved'
                            : ($ticket->status === 'closed' ? 'Your ticket was closed' : 'Ticket status updated'),
                'message' => "{$ticket->ticket_no} is now " . $ticket->statusLabel() . '.',
            ], $ticket);

            if ($ticket->assignee && ! $newlyAssigned) {
                self::send(collect([$ticket->assignee]), $actor, [
                    'kind'    => 'status',
                    'title'   => 'Ticket status changed',
                    'message' => "{$ticket->ticket_no} is now " . $ticket->statusLabel() . " (by {$actor->name}).",
                ], $ticket);
            }
        }

        if ($ticket->priority !== $old['priority'] && $ticket->assignee && ! $newlyAssigned) {
            self::send(collect([$ticket->assignee]), $actor, [
                'kind'    => 'priority',
                'title'   => 'Priority changed',
                'message' => "{$ticket->ticket_no} is now " . $ticket->priorityLabel() . ' priority.',
            ], $ticket);
        }
    }

    public static function commentAdded(Ticket $ticket, TicketComment $comment, User $actor): void
    {
        $ticket->loadMissing('user', 'assignee');
        $excerpt = Str::limit($comment->body, 90);

        if ($comment->is_internal) {
            // Staff-only note: the requester must never see it.
            self::send(collect([$ticket->assignee]), $actor, [
                'kind'    => 'note',
                'title'   => "Internal note on {$ticket->ticket_no}",
                'message' => "{$actor->name}: {$excerpt}",
            ], $ticket);

            return;
        }

        if ($actor->isStaff()) {
            // Reply to the partner, and keep the engineer in the loop.
            self::send(collect([$ticket->user, $ticket->assignee]), $actor, [
                'kind'    => 'reply',
                'title'   => "{$actor->name} replied on {$ticket->ticket_no}",
                'message' => $excerpt,
            ], $ticket);

            return;
        }

        // Partner replied: tell the assigned engineer, or the whole team if nobody has it yet.
        self::send($ticket->assignee ? collect([$ticket->assignee]) : self::admins($ticket), $actor, [
            'kind'    => 'reply',
            'title'   => "{$actor->name} replied on {$ticket->ticket_no}",
            'message' => $excerpt,
        ], $ticket);
    }

    public static function ticketRescheduled(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing(['user', 'assignee']);

        self::send(collect([$ticket->user, $ticket->assignee]), $actor, [
            'kind'    => 'status',
            'title'   => "Schedule updated {$ticket->ticket_no}",
            'message' => $ticket->whenLabel() . ' - ' . $ticket->subject,
        ], $ticket);
    }

    public static function supportTypeSet(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('user');

        self::send(collect([$ticket->user]), $actor, [
            'kind'    => 'status',
            'title'   => "How we will help {$ticket->ticket_no}",
            'message' => $ticket->support_type === 'onsite'
                ? 'An engineer will visit you on-site. Please keep your address and phone number up to date.'
                : 'Your ticket will be handled remotely. An engineer may contact you to start a session.',
        ], $ticket);
    }

    public static function ticketReopened(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('assignee');

        self::send($ticket->assignee ? collect([$ticket->assignee]) : self::admins($ticket), $actor, [
            'kind'    => 'status',
            'title'   => "Ticket reopened {$ticket->ticket_no}",
            'message' => "{$actor->name} says the problem is not fixed yet: {$ticket->subject}",
        ], $ticket);
    }

    public static function ticketFeedback(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('assignee');

        self::send(collect([$ticket->assignee]), $actor, [
            'kind'    => 'resolved',
            'title'   => "{$ticket->ticket_no} confirmed and rated",
            'message' => "{$actor->name} rated the support {$ticket->rating}/5.",
        ], $ticket);
    }

    public static function passwordReset(User $target, User $actor): void
    {
        self::dispatch(collect([$target]), $actor, [
            'kind'    => 'security',
            'title'   => 'Your password was reset',
            'message' => 'An administrator reset your password. Please change it from My Profile.',
            'url'     => route('profile.edit', absolute: false),
        ]);
    }

    // ---------------------------------------------------------------------

    /** Admins triage new tickets: they accept them and assign an engineer. */
    private static function admins(Ticket $ticket): Collection
    {
        // The admins of the ticket's own company, plus JMS itself: super admins and JMS admins (who dispatch JMS engineers).
        // An old admin who was never placed in any company is not JMS, so they are not included.
        $jmsCompanies = \App\Models\Company::jmsIds();

        return User::where(fn ($q) => $q->where('role', 'super_admin')
            ->orWhere(fn ($c) => $c->where('role', 'admin')->where(fn ($w) => $w
                ->where('company_id', $ticket->company_id)
                ->orWhere(fn ($j) => $j->whereNull('company_id')->where('company', User::JMS_NAME))
                ->orWhereIn('company_id', $jmsCompanies))))->get();
    }

    private static function send(Collection $users, User $actor, array $payload, Ticket $ticket): void
    {
        self::dispatch($users, $actor, $payload + [
            'ticket_id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'url'       => route('tickets.show', $ticket, absolute: false),
        ]);
    }

    private static function dispatch(Collection $users, User $actor, array $payload): void
    {
        $recipients = $users->filter()->unique('id')->reject(fn (User $u) => $u->id === $actor->id);

        if ($recipients->isEmpty()) {
            return;
        }

        // A notification problem must never break the action the person just did.
        rescue(fn () => Notification::send($recipients, new TicketActivity($payload)));
    }
}
