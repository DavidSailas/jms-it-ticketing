<?php

namespace App\Support;

use App\Events\NotificationPushed;
use App\Events\TicketChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Live updates through Laravel Reverb (websockets).
 *
 * Everything here is OFF until BROADCAST_CONNECTION=reverb and the REVERB_* keys are set. The pages keep their normal
 * polling as a safety net, and a broadcast problem never breaks the action the person just did.
 *
 * Channels (all private, authorised in routes/channels.php):
 *   user.{id}     one person: their own tickets, assignments and notifications
 *   company.{id}  the admins of one partner company: that company's tickets
 *   staff.jms     JMS super admins and JMS admins: every ticket
 */
class Realtime
{
    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key'));
    }

    /** Who may listen to a channel. Used by routes/channels.php. */
    public static function canListen(User $user, string $channel, ?int $id = null): bool
    {
        return match ($channel) {
            'user'    => $id !== null && (int) $user->id === $id,
            'company' => $id !== null && $user->role === 'admin' && (int) $user->company_id === $id,
            'staff'   => $user->role === 'super_admin' || $user->isJmsAdmin(),
            default   => false,
        };
    }

    /** What the browser needs to connect (null when live updates are off, so nothing is loaded). */
    public static function clientConfig(?User $user): ?array
    {
        if (! $user || ! self::enabled()) {
            return null;
        }

        $client = config('broadcasting.connections.reverb.client', []);
        $channels = ["user.{$user->id}"];

        if ($user->role === 'admin' && $user->company_id) {
            $channels[] = "company.{$user->company_id}";
        }
        if (self::canListen($user, 'staff')) {
            $channels[] = 'staff.jms';
        }

        return [
            'key'      => config('broadcasting.connections.reverb.key'),
            'host'     => $client['host'] ?? null,
            'port'     => (int) ($client['port'] ?? 443),
            'scheme'   => $client['scheme'] ?? 'https',
            'channels' => $channels,
        ];
    }

    /**
     * Tell the right people's browsers that a ticket changed.
     * $alsoUsers: people who just lost the ticket (a reassigned engineer) and still need to see it disappear.
     */
    public static function ticketChanged(Ticket $ticket, User $actor, string $kind, bool $internal = false, array $alsoUsers = []): void
    {
        if (! self::enabled()) {
            return;
        }

        $channels = ['staff.jms'];

        if ($ticket->company_id) {
            $channels[] = "company.{$ticket->company_id}";
        }
        foreach (array_filter([$ticket->assigned_to, ...$alsoUsers]) as $id) {
            $channels[] = "user.{$id}";
        }
        // A staff-only note must not even nudge the requester's page.
        if (! $internal && $ticket->user_id) {
            $channels[] = "user.{$ticket->user_id}";
        }

        rescue(fn () => event(new TicketChanged([
            'uid'       => (string) Str::uuid(),
            'kind'      => $kind,
            'ticket_id' => $ticket->id,
            'ticket_no' => $ticket->ticket_no,
            'status'    => $ticket->status,
            'actor_id'  => $actor->id,
        ], array_values(array_unique($channels)))));
    }

    /** Make these people's bells fetch right now. */
    public static function notified(Collection $recipients): void
    {
        if (! self::enabled() || $recipients->isEmpty()) {
            return;
        }

        rescue(fn () => event(new NotificationPushed($recipients->pluck('id')->map(fn ($id) => (int) $id)->all(), (string) Str::uuid())));
    }
}
