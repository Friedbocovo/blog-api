<?php

namespace App\Http\Controllers;

use App\Events\NewNotification;
use App\Models\Comment;
use App\Models\CommentMention;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * CommentController — comment CRUD, replies, and @mentions.
 *
 * Validates: Requirements 5
 */
class CommentController extends Controller
{
    // -------------------------------------------------------------------------
    // Public
    // -------------------------------------------------------------------------

    /**
     * GET /api/posts/{id}/comments
     *
     * Return root comments (parent_id IS NULL) for a post with eager-loaded
     * replies and users. Public — no authentication required.
     *
     * Validates: Requirement 5.1
     */
    public function index(string $postId): JsonResponse
    {
        // Accepte slug (string) ou ID (numeric string)
        $post = is_numeric($postId)
            ? Post::findOrFail((int) $postId)
            : Post::where('slug', $postId)->firstOrFail();

        $comments = Comment::where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with('replies.user', 'user')
            ->get();

        return response()->json($comments);
    }

    // -------------------------------------------------------------------------
    // Authenticated
    // -------------------------------------------------------------------------

    /**
     * POST /api/posts/{id}/comments
     *
     * Create a root comment on a post. Parses @mentions and dispatches a
     * NewNotification event to the post author (if different from the commenter).
     *
     * Validates: Requirements 5.2, 5.4
     */
    public function store(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $post = Post::findOrFail($postId);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $request->input('content'),
        ]);

        $this->parseMentions($comment, $request->input('content'));

        // Notify post author if they are not the commenter
        if ($post->user_id !== $request->user()->id) {
            try {
                // Create database notification
                $post->user->notifications()->create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'App\\Notifications\\NewComment',
                    'data' => [
                        'type' => 'new_comment',
                        'comment_id' => $comment->id,
                        'post_id' => $post->id,
                        'post_title' => $post->title,
                        'commenter_name' => $request->user()->name,
                        'commenter_id' => $request->user()->id,
                        'comment_content' => $comment->content,
                        'message' => $request->user()->name . ' a commenté votre article "' . $post->title . '"',
                    ],
                    'read_at' => null,
                ]);
            } catch (\Throwable) {
                // Database notification failure should not block event dispatch
            }

            Event::dispatch(new NewNotification($post->user_id, [
                'type' => 'new_comment',
                'comment_id' => $comment->id,
                'post_id' => $post->id,
                'post_title' => $post->title,
                'commenter_name' => $request->user()->name,
                'commenter_id' => $request->user()->id,
                'comment_content' => $comment->content,
                'message' => $request->user()->name . ' a commenté votre article "' . $post->title . '"',
            ]));
        }

        $comment->load('user');

        return response()->json($comment, 201);
    }

    /**
     * POST /api/comments/{id}/reply
     *
     * Create a reply to an existing comment. Shares the same post and sets
     * parent_id. Also parses @mentions and sends notifications.
     *
     * Validates: Requirements 5.3, 5.4
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $parent = Comment::with(['user', 'post'])->findOrFail($id);

        $comment = Comment::create([
            'post_id'   => $parent->post_id,
            'user_id'   => $request->user()->id,
            'parent_id' => $parent->id,
            'content'   => $request->input('content'),
        ]);

        $this->parseMentions($comment, $request->input('content'));

        // Notify the original commenter if they are not the replier
        if ($parent->user_id !== $request->user()->id) {
            try {
                // Create database notification
                $parent->user->notifications()->create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'type' => 'App\\Notifications\\CommentReply',
                    'data' => [
                        'type' => 'comment_reply',
                        'comment_id' => $comment->id,
                        'parent_comment_id' => $parent->id,
                        'post_id' => $parent->post_id,
                        'post_title' => $parent->post->title,
                        'replier_name' => $request->user()->name,
                        'replier_id' => $request->user()->id,
                        'reply_content' => $comment->content,
                        'message' => $request->user()->name . ' a répondu à votre commentaire',
                    ],
                    'read_at' => null,
                ]);
            } catch (\Throwable) {
                // Database notification failure should not block event dispatch
            }

            Event::dispatch(new NewNotification($parent->user_id, [
                'type' => 'comment_reply',
                'comment_id' => $comment->id,
                'parent_comment_id' => $parent->id,
                'post_id' => $parent->post_id,
                'post_title' => $parent->post->title,
                'replier_name' => $request->user()->name,
                'replier_id' => $request->user()->id,
                'reply_content' => $comment->content,
                'message' => $request->user()->name . ' a répondu à votre commentaire',
            ]));
        }

        $comment->load('user');

        return response()->json($comment, 201);
    }

    /**
     * PUT /api/comments/{id}
     *
     * Update a comment's content. Only the author may update their own comment.
     *
     * Validates: Requirements 5.5, 5.7
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update(['content' => $request->input('content')]);

        return response()->json($comment);
    }

    /**
     * DELETE /api/comments/{id}
     *
     * Delete a comment (and its replies via DB cascade). Allowed for the
     * comment author or a user with the admin role.
     *
     * Validates: Requirements 5.6, 5.7
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        $isAuthor = $comment->user_id === $request->user()->id;
        $isAdmin  = $request->user()->role === 'admin';

        if (!$isAuthor && !$isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment->delete();

        return response()->json(null, 204);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Parse @mentions in the given content and create CommentMention records.
     *
     * Validates: Requirement 5.4
     */
    private function parseMentions(Comment $comment, string $content): void
    {
        preg_match_all('/@(\w+)/', $content, $matches);

        foreach ($matches[1] as $username) {
            $mentioned = User::where('name', $username)->first();

            if ($mentioned) {
                CommentMention::create([
                    'comment_id'          => $comment->id,
                    'mentioned_user_id'   => $mentioned->id,
                ]);
            }
        }
    }
}
