<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * One in-app (database) notification. The payload is stored as JSON in the
 * `notifications` table: kind, title, message, url, ticket_id, ticket_no.
 */
class TicketActivity extends Notification
{
    public function __construct(public array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
