<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Returns 201 on success, 409 if already liked, 404 if post not found.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        try {
            $request->user()->likedPosts()->attach($post->id);
        } catch (QueryException $e) {
            // MySQL unique constraint violation — duplicate like
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'Already liked.'], 409);
            }

            throw $e;
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
