<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NewNotification event — broadcast on the private user channel.
 *
 * Fired whenever a notification-triggering event occurs (new like, comment,
 * message, or mention). The WebSocket emission is independent of database
 * persistence.
 *
 * Validates: Requirements 7.4, 11.2
 */
class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param int   $userId  The ID of the user who should receive the notification.
     * @param array $data    Notification payload to send to the client.
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $data = [],
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * The event name used on the client side.
     */
    public function broadcastAs(): string
    {
        return 'NewNotification';
    }

    /**
     * The data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->data;
    }
}
