<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PostController — public post endpoints.
 *
 * Validates: Requirements 3
 */
class PostController extends Controller
{
    /**
     * GET /api/posts
     *
     * Returns published posts, pinned first, paginated (10/page).
     * Supports optional ?tag=<slug> and ?search=<term> filters.
     *
     * Validates: Requirements 3.1, 3.4, 3.5
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::where('status', 'published')
            ->with(['tags', 'user']);

        // Filter by tag slug
        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->input('tag'));
            });
        }

        // Full-text search on title or excerpt
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', '%' . $term . '%')
                  ->orWhere('excerpt', 'like', '%' . $term . '%');
            });
        }

        // Pinned first, then by published_at descending
        $query->orderByDesc('pinned')
              ->orderByDesc('published_at');

        $posts = $query->paginate(10);

        return response()->json($posts);
    }

    /**
     * GET /api/posts/pinned
     *
     * Returns all published pinned posts.
     *
     * Validates: Requirements 3.2
     */
    public function pinned(): JsonResponse
    {
        $posts = Post::where('status', 'published')
            ->where('pinned', true)
            ->with(['tags', 'user'])
            ->orderByDesc('published_at')
            ->get();

        return response()->json($posts);
    }

    /**
     * GET /api/posts/{slug}
     *
     * Returns the full post data for the given slug.
     * Increments views_count. Returns 404 if draft or not found.
     *
     * Validates: Requirements 3.3, 3.6
     */
    public function show(string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        // Draft posts are not visible to the public
        if ($post->status === 'draft') {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Increment view counter
        $post->increment('views_count');

        // Reload with all relations needed for a full post view
        $post->load(['user', 'tags', 'comments.user']);

        return response()->json($post);
    }
}
