<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NewMessage event — broadcast on the receiver's private chat channel.
 *
 * Fired whenever a new message is created via POST /api/messages.
 *
 * Validates: Requirements 6.3, 11.1
 */
class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public readonly Message $message)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->receiver_id),
        ];
    }

    /**
     * The event name used on the client side.
     */
    public function broadcastAs(): string
    {
        return 'NewMessage';
    }

    /**
     * The data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'        => $this->message->id,
            'content'   => $this->message->content,
            'sender_id' => $this->message->sender_id,
        ];
    }
}
