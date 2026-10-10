<?php

namespace App\Support;

use App\Models\Ticket;

/** The few ticket fields the quick-assign panel needs, handed to Alpine as JSON. */
class AssignPayload
{
    public static function for(Ticket $t): array
    {
        return [
            'id'           => $t->id,
            'no'           => $t->ticket_no,
            'subject'      => $t->subject,
            'requester'    => $t->user?->name,
            'assigned_to'  => $t->assigned_to,
            'support_type' => $t->support_type,
            'priority'     => $t->priority,
            'action'       => route('tickets.assign', $t),
            'url'          => route('tickets.show', $t),
        ];
    }
}
