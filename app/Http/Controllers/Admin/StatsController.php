<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Admin\StatsController — global blog statistics.
 *
 * Validates: Requirements 2.8
 */
class StatsController extends Controller
{
    /**
     * GET /api/admin/posts/stats
     *
     * Returns aggregate statistics for the whole blog:
     *  - total_posts      : all posts (published + draft)
     *  - published_posts  : posts with status = published
     *  - draft_posts      : posts with status = draft
     *  - total_views      : sum of views_count across all posts
     *  - total_likes      : total entries in post_likes table
     *  - total_comments   : total entries in comments table
     *
     * Validates: Requirements 2.8
     */
    public function index(): JsonResponse
    {
        $totalPosts     = Post::count();
        $publishedPosts = Post::where('status', 'published')->count();
        $draftPosts     = Post::where('status', 'draft')->count();
        $totalViews     = (int) Post::sum('views_count');
        $totalLikes     = (int) DB::table('post_likes')->count();
        $totalComments  = Comment::count();

        return response()->json([
            'total_posts'     => $totalPosts,
            'published_posts' => $publishedPosts,
            'draft_posts'     => $draftPosts,
            'total_views'     => $totalViews,
            'total_likes'     => $totalLikes,
            'total_comments'  => $totalComments,
        ]);
    }
}
