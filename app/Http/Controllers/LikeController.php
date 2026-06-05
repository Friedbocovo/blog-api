<?php

namespace App\Http\Controllers;

use App\Events\NewNotification;
use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * LikeController — post like interactions.
 *
 * Validates: Requirements 4
 */
class LikeController extends Controller
{
    /**
     * POST /api/posts/{id}/like
     *
     * Create a like for the authenticated user on the given post.
     * Notifies the post author if they are different from the liker.
     * Returns 201 on success, 409 if already liked, 404 if post not found.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $post = Post::with('user')->findOrFail($id);

        try {
            $request->user()->likedPosts()->attach($post->id);
        } catch (QueryException $e) {
            // MySQL unique constraint violation — duplicate like
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'Already liked.'], 409);
            }

            throw $e;
        }

        // Send notification to post author (if different from liker)
        if ($post->user_id !== $request->user()->id) {
            try {
                // Create database notification
                $notificationData = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'App\\Notifications\\NewLike',
                    'data' => [
                        'type' => 'new_like',
                        'post_id' => $post->id,
                        'post_title' => $post->title,
                        'liker_name' => $request->user()->name,
                        'liker_id' => $request->user()->id,
                        'message' => $request->user()->name . ' a liké votre article "' . $post->title . '"',
                    ],
                    'read_at' => null,
                ];
                
                $post->user->notifications()->create($notificationData);
            } catch (\Throwable $e) {
                // Database notification failure should not block response
                \Log::info('Like notification failed: ' . $e->getMessage());
            }

            // Dispatch WebSocket notification (independent of DB persistence)
            Event::dispatch(new NewNotification($post->user_id, [
                'type' => 'new_like',
                'post_id' => $post->id,
                'post_title' => $post->title,
                'liker_name' => $request->user()->name,
                'liker_id' => $request->user()->id,
                'message' => $request->user()->name . ' a liké votre article "' . $post->title . '"',
            ]));
        }

        return response()->json(['message' => 'Post liked.'], 201);
    }

    /**
     * DELETE /api/posts/{id}/like
     *
     * Remove the like of the authenticated user on the given post.
     * Returns 204 on success, 404 if the like does not exist.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->likedPosts()->detach($id);

        if ($deleted === 0) {
            return response()->json(['message' => 'Like not found.'], 404);
        }

        return response()->json(null, 204);
    }
}
