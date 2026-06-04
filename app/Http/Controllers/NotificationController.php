<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * NotificationController — in-app notifications.
 *
 * Validates: Requirements 7
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     *
     * Return the authenticated user's notifications ordered by date descending.
     *
     * Validates: Requirement 7.1
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * PATCH /api/notifications/read-all
     *
     * Mark all unread notifications for the authenticated user as read.
     *
     * Validates: Requirement 7.2
     */
    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * PATCH /api/notifications/{id}/read
     *
     * Mark a specific notification as read.
     *
     * Validates: Requirement 7.3
     */
    public function read(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json($notification->fresh());
    }
}
