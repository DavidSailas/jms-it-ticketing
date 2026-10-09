<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Something changed on this ticket." It carries no ticket text, only ids: the browser reacts by re-fetching what it
 * is already allowed to see through the normal pages and feeds. Sent immediately (no queue worker needed).
 */
class TicketChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<string,mixed> $payload  @param list<string> $channels  e.g. ['staff.jms', 'user.5'] */
    public function __construct(public array $payload, public array $channels) {}

    public function broadcastOn(): array
    {
        return array_map(fn (string $name) => new PrivateChannel($name), $this->channels);
    }

    public function broadcastAs(): string
    {
        return 'ticket.changed';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
