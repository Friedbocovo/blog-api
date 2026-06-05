<?php

namespace App\Http\Controllers;

use App\Events\NewMessage;
use App\Events\NewNotification;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MessageController — private messaging.
 *
 * Validates: Requirements 6.3, 6.4, 6.5
 */
class MessageController extends Controller
{
    /**
     * POST /api/messages
     *
     * Validate that sender and receiver have different roles, create the message,
     * and broadcast the NewMessage event. Also dispatches NewNotification to
     * the receiver.
     *
     * Requirements 6.3, 6.5
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'content'     => ['required', 'string'],
        ]);

        /** @var \App\Models\User $sender */
        $sender   = $request->user();
        $receiver = User::findOrFail($validated['receiver_id']);

        // Requirement 6.5 — messaging restricted to admin ↔ visitor exchanges
        if ($sender->role === $receiver->role) {
            return response()->json([
                'message' => 'Cannot send messages between users of the same role',
            ], 422);
        }

        $message = Message::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => $validated['content'],
        ]);

        // Broadcast NewMessage on private-chat.{receiverId}
        event(new NewMessage($message));

        // Create database notification and dispatch WebSocket notification
        try {
            // Create database notification
            $receiver->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\NewMessage',
                'data' => [
                    'type' => 'new_message',
                    'message_id' => $message->id,
                    'message_content' => $message->content,
                    'sender_name' => $sender->name,
                    'sender_id' => $sender->id,
                    'message' => 'Nouveau message de ' . $sender->name,
                ],
                'read_at' => null,
            ]);
        } catch (\Throwable) {
            // Database notification failure should not block WebSocket dispatch
        }

        // Dispatch WebSocket notification (independent of DB persistence)  
        try {
            event(new NewNotification($receiver->id, [
                'type' => 'new_message',
                'message_id' => $message->id,
                'message_content' => $message->content,
                'sender_name' => $sender->name,
                'sender_id' => $sender->id,
                'message' => 'Nouveau message de ' . $sender->name,
            ]));
        } catch (\Throwable $e) {
            // Intentionally swallowed — WebSocket notification is best-effort
        }

        $message->load('sender');

        return response()->json($message, 201);
    }

    /**
     * PATCH /api/messages/{id}/read
     *
     * Set read_at to the current timestamp.
     *
     * Requirement 6.4
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $message = Message::findOrFail($id);

        $message->update(['read_at' => now()]);

        return response()->json($message);
    }
}
