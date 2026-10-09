<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** "You have a new notification": the bell fetches its feed right away instead of waiting for its next poll. */
class NotificationPushed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param list<int> $userIds */
    public function __construct(public array $userIds, public string $uid) {}

    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel("user.{$id}"), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'notification.pushed';
    }

    public function broadcastWith(): array
    {
        return ['uid' => $this->uid];
    }
}
