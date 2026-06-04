<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ConversationController — conversation history.
 *
 * Validates: Requirements 6.1, 6.2
 */
class ConversationController extends Controller
{
    /**
     * GET /api/conversations
     *
     * Return all conversations for the authenticated user.
     * Each entry contains the other user's info, the last message exchanged,
     * and the count of unread messages (sent by the other user).
     *
     * Requirement 6.1
     */
    public function index(Request $request): JsonResponse
    {
        $authId = $request->user()->id;

        // Find all unique interlocutor IDs
        $interlocutorIds = Message::where(function ($query) use ($authId) {
            $query->where('sender_id', $authId)
                  ->orWhere('receiver_id', $authId);
        })
        ->selectRaw('
            CASE
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END AS interlocutor_id
        ', [$authId])
        ->distinct()
        ->pluck('interlocutor_id');

        $conversations = $interlocutorIds->map(function (int $otherId) use ($authId) {
            $lastMessage = Message::where(function ($q) use ($authId, $otherId) {
                $q->where('sender_id', $authId)->where('receiver_id', $otherId);
            })->orWhere(function ($q) use ($authId, $otherId) {
                $q->where('sender_id', $otherId)->where('receiver_id', $authId);
            })
            ->orderByDesc('created_at')
            ->first();

            $unreadCount = Message::where('sender_id', $otherId)
                ->where('receiver_id', $authId)
                ->whereNull('read_at')
                ->count();

            $other = User::find($otherId);

            return [
                'user'         => $other,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
            ];
        });

        return response()->json($conversations->values());
    }

    /**
     * GET /api/conversations/{userId}
     *
     * Return all messages exchanged between the authenticated user and the
     * given user, ordered by created_at ASC.
     *
     * Requirement 6.2
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $authId = $request->user()->id;

        $messages = Message::where(function ($query) use ($authId, $userId) {
            $query->where('sender_id', $authId)->where('receiver_id', $userId);
        })->orWhere(function ($query) use ($authId, $userId) {
            $query->where('sender_id', $userId)->where('receiver_id', $authId);
        })
        ->with(['sender', 'receiver'])
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($messages);
    }
}
