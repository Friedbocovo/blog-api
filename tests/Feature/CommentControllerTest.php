<?php

namespace Tests\Feature;

use App\Events\NewNotification;
use App\Models\Comment;
use App\Models\CommentMention;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Feature tests for CommentController.
 *
 * Validates: Requirements 5
 */
class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createVisitor(): User
    {
        return User::factory()->create(['role' => 'visitor']);
    }

    private function createPublishedPost(?User $author = null): Post
    {
        $author ??= $this->createAdmin();

        return Post::factory()->create([
            'user_id' => $author->id,
            'status'  => 'published',
        ]);
    }

    // =========================================================================
    // GET /api/posts/{id}/comments
    // =========================================================================

    /**
     * Requirement 5.1 — Returns nested comments with replies, publicly accessible.
     */
    public function test_index_returns_nested_comments_with_replies(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        // Root comment by visitor
        $root = Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $visitor->id,
            'parent_id' => null,
            'content'   => 'Root comment',
        ]);

        // Reply to root comment by admin
        Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $admin->id,
            'parent_id' => $root->id,
            'content'   => 'Reply comment',
        ]);

        // No auth required
        $response = $this->getJson("/api/posts/{$post->id}/comments");

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data); // only root-level comments

        $firstComment = $data[0];
        $this->assertEquals('Root comment', $firstComment['content']);
        $this->assertArrayHasKey('user', $firstComment);
        $this->assertArrayHasKey('replies', $firstComment);
        $this->assertCount(1, $firstComment['replies']);
        $this->assertArrayHasKey('user', $firstComment['replies'][0]);
    }

    /**
     * Requirement 5.1 — Root-level replies are not returned as top-level comments.
     */
    public function test_index_does_not_return_replies_as_top_level_comments(): void
    {
        $admin = $this->createAdmin();
        $post  = $this->createPublishedPost($admin);

        $root = Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $admin->id,
            'parent_id' => null,
        ]);

        // Reply — should NOT appear as top-level
        Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $admin->id,
            'parent_id' => $root->id,
        ]);

        $response = $this->getJson("/api/posts/{$post->id}/comments");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    // =========================================================================
    // POST /api/posts/{id}/comments
    // =========================================================================

    /**
     * Requirement 5.2 — Authenticated user can post a comment and receives 201.
     */
    public function test_store_creates_comment_and_returns_201(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $response = $this->actingAs($visitor, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", [
                'content' => 'A great post!',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['content' => 'A great post!']);
        $response->assertJsonStructure(['id', 'content', 'user', 'post_id', 'user_id']);

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $visitor->id,
            'content' => 'A great post!',
        ]);
    }

    /**
     * Requirement 5.2 — Response includes the user relation.
     */
    public function test_store_returns_comment_with_user_in_response(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $response = $this->actingAs($visitor, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(201);
        $this->assertArrayHasKey('user', $response->json());
        $this->assertEquals($visitor->id, $response->json('user.id'));
    }

    /**
     * Requirement 5.4 — @mention creates a CommentMention record.
     */
    public function test_store_creates_comment_mention_for_at_mention(): void
    {
        Event::fake();

        $admin     = $this->createAdmin();
        // Use a single-word name so the @mention regex can match it exactly
        $mentioned = User::factory()->create(['role' => 'visitor', 'name' => 'JaneDoe']);
        $commenter = $this->createVisitor();
        $post      = $this->createPublishedPost($admin);

        $response = $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", [
                'content' => "Hey @{$mentioned->name}, check this out!",
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comment_mentions', [
            'comment_id'        => $response->json('id'),
            'mentioned_user_id' => $mentioned->id,
        ]);
    }

    /**
     * Requirement 5 — Posting without authentication returns 401.
     */
    public function test_store_returns_401_without_auth(): void
    {
        $post = $this->createPublishedPost();

        $this->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'Hello!',
        ])->assertStatus(401);
    }

    /**
     * Requirement 5.2 — Missing content returns 422.
     */
    public function test_store_returns_422_when_content_missing(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $this->actingAs($visitor, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", [])
            ->assertStatus(422);
    }

    // =========================================================================
    // POST /api/comments/{id}/reply
    // =========================================================================

    /**
     * Requirement 5.3 — Reply sets parent_id to the parent comment's id.
     */
    public function test_reply_creates_comment_with_parent_id(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $parent = Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $admin->id,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($visitor, 'sanctum')
            ->postJson("/api/comments/{$parent->id}/reply", [
                'content' => 'Great reply!',
            ]);

        $response->assertStatus(201);
        $this->assertEquals($parent->id, $response->json('parent_id'));
        $this->assertEquals($post->id, $response->json('post_id'));

        $this->assertDatabaseHas('comments', [
            'parent_id' => $parent->id,
            'user_id'   => $visitor->id,
            'content'   => 'Great reply!',
        ]);
    }

    /**
     * Requirement 5.4 — Reply with @mention creates CommentMention record.
     */
    public function test_reply_creates_mention_for_at_mention(): void
    {
        Event::fake();

        $admin     = $this->createAdmin();
        // Use a single-word name so the @mention regex can match it exactly
        $mentioned = User::factory()->create(['role' => 'visitor', 'name' => 'BobSmith']);
        $replier   = $this->createVisitor();
        $post      = $this->createPublishedPost($admin);

        $parent = Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $admin->id,
            'parent_id' => null,
        ]);

        $response = $this->actingAs($replier, 'sanctum')
            ->postJson("/api/comments/{$parent->id}/reply", [
                'content' => "@{$mentioned->name} thanks!",
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comment_mentions', [
            'comment_id'        => $response->json('id'),
            'mentioned_user_id' => $mentioned->id,
        ]);
    }

    /**
     * Requirement 5.3 — Reply returns 404 for non-existent parent comment.
     */
    public function test_reply_returns_404_for_nonexistent_parent(): void
    {
        $visitor = $this->createVisitor();

        $this->actingAs($visitor, 'sanctum')
            ->postJson('/api/comments/99999/reply', [
                'content' => 'Reply to nothing',
            ])->assertStatus(404);
    }

    // =========================================================================
    // PUT /api/comments/{id}
    // =========================================================================

    /**
     * Requirement 5.5 — Author can update their own comment and gets 200.
     */
    public function test_update_allows_author_to_modify_comment(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $visitor->id,
            'content' => 'Original content',
        ]);

        $response = $this->actingAs($visitor, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'content' => 'Updated content',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['content' => 'Updated content']);

        $this->assertDatabaseHas('comments', [
            'id'      => $comment->id,
            'content' => 'Updated content',
        ]);
    }

    /**
     * Requirement 5.7 — Non-author cannot update the comment and gets 403.
     */
    public function test_update_returns_403_for_non_author(): void
    {
        $admin     = $this->createAdmin();
        $author    = $this->createVisitor();
        $otherUser = $this->createVisitor();
        $post      = $this->createPublishedPost($admin);

        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => 'Original content',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/comments/{$comment->id}", [
                'content' => 'Hacked content',
            ])->assertStatus(403);

        // Content must not have changed
        $this->assertDatabaseHas('comments', [
            'id'      => $comment->id,
            'content' => 'Original content',
        ]);
    }

    /**
     * Requirement 5.5 — Returns 404 for non-existent comment.
     */
    public function test_update_returns_404_for_nonexistent_comment(): void
    {
        $visitor = $this->createVisitor();

        $this->actingAs($visitor, 'sanctum')
            ->putJson('/api/comments/99999', [
                'content' => 'Test',
            ])->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/comments/{id}
    // =========================================================================

    /**
     * Requirement 5.6 — Author can delete their own comment and gets 204.
     */
    public function test_destroy_allows_author_to_delete_comment(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $visitor->id,
        ]);

        $response = $this->actingAs($visitor, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    /**
     * Requirement 5.6 — Admin can delete any comment and gets 204.
     */
    public function test_destroy_allows_admin_to_delete_any_comment(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $visitor->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    /**
     * Requirement 5.7 — Non-author non-admin user gets 403 when deleting.
     */
    public function test_destroy_returns_403_for_other_visitor(): void
    {
        $admin     = $this->createAdmin();
        $author    = $this->createVisitor();
        $otherUser = $this->createVisitor();
        $post      = $this->createPublishedPost($admin);

        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    /**
     * Requirement 5 — Delete without authentication returns 401.
     */
    public function test_destroy_returns_401_without_auth(): void
    {
        $post    = $this->createPublishedPost();
        $comment = Comment::factory()->create(['post_id' => $post->id]);

        $this->deleteJson("/api/comments/{$comment->id}")
            ->assertStatus(401);
    }

    /**
     * Property 8 — Deleting a root comment cascades to its replies.
     */
    public function test_destroy_cascades_to_replies(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost($admin);

        $root = Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $visitor->id,
            'parent_id' => null,
        ]);

        $reply = Comment::factory()->create([
            'post_id'   => $post->id,
            'user_id'   => $admin->id,
            'parent_id' => $root->id,
        ]);

        $this->actingAs($visitor, 'sanctum')
            ->deleteJson("/api/comments/{$root->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('comments', ['id' => $root->id]);
        $this->assertDatabaseMissing('comments', ['id' => $reply->id]);
    }
}
