<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\User;

/** The one place a ticket's work status is changed (ticket page buttons and the Kanban board both use it). */
class TicketProgress
{
    /** Statuses an engineer or admin can move a ticket to. */
    public const TARGETS = ['in_progress', 'on_hold', 'resolved'];

    public static function apply(User $user, Ticket $ticket, string $status, ?string $resolution = null): Ticket
    {
        $old      = $ticket->only(['status', 'priority', 'assigned_to']);
        $resolved = $status === 'resolved';

        $ticket->update([
            'status'      => $status,
            'resolution'  => $resolved ? $resolution : $ticket->resolution,
            'resolved_at' => $resolved ? now() : null,
        ]);
        $ticket = $ticket->fresh();
        Notifier::ticketUpdated($ticket, $user, $old);

        Activity::record($user, $resolved ? 'ticket_resolved' : 'ticket_updated',
            $resolved ? "Resolved {$ticket->ticket_no}" : "Changed {$ticket->ticket_no}: status to {$ticket->statusLabel()}", $ticket);

        return $ticket;
    }
}
