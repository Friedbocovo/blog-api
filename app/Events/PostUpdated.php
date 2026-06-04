<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PostUpdated event — broadcast on the presence-online channel.
 *
 * Fired whenever a post is published, unpublished, pinned, or unpinned.
 * Validates: Requirements 11.3
 */
class PostUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public readonly Post $post)
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
            new PresenceChannel('online'),
        ];
    }

    /**
     * The event name used on the client side.
     */
    public function broadcastAs(): string
    {
        return 'PostUpdated';
    }

    /**
     * The data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id'     => $this->post->id,
            'slug'   => $this->post->slug,
            'status' => $this->post->status,
            'pinned' => $this->post->pinned,
        ];
    }
}
