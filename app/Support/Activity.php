<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * One line to write to a person's activity log:
 *     Activity::record($user, 'password_changed', 'Changed your password');
 */
class Activity
{
    public static function record(User $user, string $action, string $description, ?Ticket $ticket = null): void
    {
        // A logging problem must never break the action the person just did.
        rescue(fn () => ActivityLog::create([
            'user_id'     => $user->id,
            'category'    => ActivityKinds::category($action),
            'action'      => $action,
            'description' => Str::limit($description, 250, '...'),
            'ticket_id'   => $ticket?->id,
            'ip_address'  => request()->ip(),
            'user_agent'  => Str::limit((string) request()->userAgent(), 250, ''),
        ]));
    }
}
