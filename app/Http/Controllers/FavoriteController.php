<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FavoriteController — post favorite interactions.
 *
 * Validates: Requirements 4
 */
class FavoriteController extends Controller
{
    /**
     * POST /api/posts/{id}/favorite
     *
     * Add the given post to the authenticated user's favorites.
     * Returns 201 on success, 409 if already favorited, 404 if post not found.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        try {
            $request->user()->favoritePosts()->attach($post->id);
        } catch (QueryException $e) {
            // MySQL unique constraint violation — duplicate favorite
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'Already favorited.'], 409);
            }

            throw $e;
        }

        return response()->json(['message' => 'Post favorited.'], 201);
    }

    /**
     * DELETE /api/posts/{id}/favorite
     *
     * Remove the given post from the authenticated user's favorites.
     * Returns 204 on success, 404 if not in favorites.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->favoritePosts()->detach($id);

        if ($deleted === 0) {
            return response()->json(['message' => 'Favorite not found.'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * GET /api/user/favorites
     *
     * Return the authenticated user's favorited posts, paginated with tags and author.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favoritePosts()
            ->with(['tags', 'user'])
            ->paginate(10);

        return response()->json($favorites);
    }
}
