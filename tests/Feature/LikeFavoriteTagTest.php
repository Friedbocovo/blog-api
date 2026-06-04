<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for LikeController, FavoriteController, and TagController.
 *
 * Validates: Requirements 4, 10
 */
class LikeFavoriteTagTest extends TestCase
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

    private function createPublishedPost(?User $admin = null): Post
    {
        $admin ??= $this->createAdmin();

        return Post::factory()->create([
            'user_id' => $admin->id,
            'status'  => 'published',
        ]);
    }

    // =========================================================================
    // LikeController — POST /api/posts/{id}/like
    // =========================================================================

    /**
     * Requirement 4.1 — A connected visitor can like a post and gets 201.
     */
    public function test_like_store_returns_201_on_success(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        $response = $this->actingAs($visitor, 'sanctum')
                         ->postJson("/api/posts/{$post->id}/like");

        $response->assertStatus(201);
        $this->assertDatabaseHas('post_likes', [
            'user_id' => $visitor->id,
            'post_id' => $post->id,
        ]);
    }

    /**
     * Requirement 4.3 — Liking the same post twice returns 409 on the second call.
     */
    public function test_like_store_returns_409_on_duplicate(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        // First like — should succeed
        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/like")
             ->assertStatus(201);

        // Second like — should conflict
        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/like")
             ->assertStatus(409);

        // Still only one row in the table
        $this->assertDatabaseCount('post_likes', 1);
    }

    /**
     * Requirement 4.3 — No duplicate row is ever inserted in post_likes.
     */
    public function test_like_store_does_not_create_duplicate_row(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/like");

        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/like");

        $count = DB::table('post_likes')
            ->where('user_id', $visitor->id)
            ->where('post_id', $post->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Requirement 4 — Liking a non-existent post returns 404.
     */
    public function test_like_store_returns_404_for_nonexistent_post(): void
    {
        $visitor = $this->createVisitor();

        $this->actingAs($visitor, 'sanctum')
             ->postJson('/api/posts/99999/like')
             ->assertStatus(404);
    }

    /**
     * Requirement 4.8 — Like without authentication returns 401.
     */
    public function test_like_store_returns_401_without_auth(): void
    {
        $post = $this->createPublishedPost();

        $this->postJson("/api/posts/{$post->id}/like")
             ->assertStatus(401);
    }

    // =========================================================================
    // LikeController — DELETE /api/posts/{id}/like
    // =========================================================================

    /**
     * Requirement 4.2 — A connected visitor can unlike a post and gets 204.
     */
    public function test_like_destroy_returns_204_on_success(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        // Like first
        $visitor->likedPosts()->attach($post->id);

        $response = $this->actingAs($visitor, 'sanctum')
                         ->deleteJson("/api/posts/{$post->id}/like");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('post_likes', [
            'user_id' => $visitor->id,
            'post_id' => $post->id,
        ]);
    }

    /**
     * Requirement 4.2 — Unliking a post that was never liked returns 404.
     */
    public function test_like_destroy_returns_404_when_not_liked(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        $this->actingAs($visitor, 'sanctum')
             ->deleteJson("/api/posts/{$post->id}/like")
             ->assertStatus(404);
    }

    /**
     * Requirement 4.8 — Unlike without authentication returns 401.
     */
    public function test_like_destroy_returns_401_without_auth(): void
    {
        $post = $this->createPublishedPost();

        $this->deleteJson("/api/posts/{$post->id}/like")
             ->assertStatus(401);
    }

    // =========================================================================
    // FavoriteController — POST /api/posts/{id}/favorite
    // =========================================================================

    /**
     * Requirement 4.4 — A connected visitor can favorite a post and gets 201.
     */
    public function test_favorite_store_returns_201_on_success(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        $response = $this->actingAs($visitor, 'sanctum')
                         ->postJson("/api/posts/{$post->id}/favorite");

        $response->assertStatus(201);
        $this->assertDatabaseHas('post_favorites', [
            'user_id' => $visitor->id,
            'post_id' => $post->id,
        ]);
    }

    /**
     * Requirement 4.6 — Favoriting the same post twice returns 409.
     */
    public function test_favorite_store_returns_409_on_duplicate(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        // First favorite
        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/favorite")
             ->assertStatus(201);

        // Second favorite — should conflict
        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/favorite")
             ->assertStatus(409);

        $this->assertDatabaseCount('post_favorites', 1);
    }

    /**
     * Requirement 4.6 — No duplicate row is ever inserted in post_favorites.
     */
    public function test_favorite_store_does_not_create_duplicate_row(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/favorite");

        $this->actingAs($visitor, 'sanctum')
             ->postJson("/api/posts/{$post->id}/favorite");

        $count = DB::table('post_favorites')
            ->where('user_id', $visitor->id)
            ->where('post_id', $post->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Requirement 4.4 — Favoriting a non-existent post returns 404.
     */
    public function test_favorite_store_returns_404_for_nonexistent_post(): void
    {
        $visitor = $this->createVisitor();

        $this->actingAs($visitor, 'sanctum')
             ->postJson('/api/posts/99999/favorite')
             ->assertStatus(404);
    }

    /**
     * Requirement 4.8 — Favorite without authentication returns 401.
     */
    public function test_favorite_store_returns_401_without_auth(): void
    {
        $post = $this->createPublishedPost();

        $this->postJson("/api/posts/{$post->id}/favorite")
             ->assertStatus(401);
    }

    // =========================================================================
    // FavoriteController — DELETE /api/posts/{id}/favorite
    // =========================================================================

    /**
     * Requirement 4.5 — A connected visitor can unfavorite a post and gets 204.
     */
    public function test_favorite_destroy_returns_204_on_success(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        // Favorite first
        $visitor->favoritePosts()->attach($post->id);

        $response = $this->actingAs($visitor, 'sanctum')
                         ->deleteJson("/api/posts/{$post->id}/favorite");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('post_favorites', [
            'user_id' => $visitor->id,
            'post_id' => $post->id,
        ]);
    }

    /**
     * Requirement 4.5 — Unfavoriting a post that was never favorited returns 404.
     */
    public function test_favorite_destroy_returns_404_when_not_favorited(): void
    {
        $visitor = $this->createVisitor();
        $post    = $this->createPublishedPost();

        $this->actingAs($visitor, 'sanctum')
             ->deleteJson("/api/posts/{$post->id}/favorite")
             ->assertStatus(404);
    }

    // =========================================================================
    // FavoriteController — GET /api/user/favorites
    // =========================================================================

    /**
     * Requirement 4.7 — Returns the authenticated user's favorited posts.
     */
    public function test_favorites_index_returns_user_favorites(): void
    {
        $visitor = $this->createVisitor();
        $admin   = $this->createAdmin();

        $post1 = $this->createPublishedPost($admin);
        $post2 = $this->createPublishedPost($admin);
        $post3 = $this->createPublishedPost($admin); // not favorited

        $visitor->favoritePosts()->attach([$post1->id, $post2->id]);

        $response = $this->actingAs($visitor, 'sanctum')
                         ->getJson('/api/user/favorites');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($post1->id, $ids);
        $this->assertContains($post2->id, $ids);
        $this->assertNotContains($post3->id, $ids);
    }

    /**
     * Requirement 4.7 — Does not return another user's favorites.
     */
    public function test_favorites_index_only_returns_own_favorites(): void
    {
        $visitor1 = $this->createVisitor();
        $visitor2 = $this->createVisitor();
        $admin    = $this->createAdmin();

        $post = $this->createPublishedPost($admin);

        // Only visitor2 favorites the post
        $visitor2->favoritePosts()->attach($post->id);

        $response = $this->actingAs($visitor1, 'sanctum')
                         ->getJson('/api/user/favorites');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    /**
     * Requirement 4.7 — Favorites response includes tags and user relations.
     */
    public function test_favorites_index_includes_tags_and_user(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();
        $tag     = Tag::factory()->create();

        $post = $this->createPublishedPost($admin);
        $post->tags()->attach($tag);

        $visitor->favoritePosts()->attach($post->id);

        $response = $this->actingAs($visitor, 'sanctum')
                         ->getJson('/api/user/favorites');

        $response->assertOk();
        $firstPost = $response->json('data.0');
        $this->assertArrayHasKey('tags', $firstPost);
        $this->assertArrayHasKey('user', $firstPost);
    }

    /**
     * Requirement 4.8 — Favorites without authentication returns 401.
     */
    public function test_favorites_index_returns_401_without_auth(): void
    {
        $this->getJson('/api/user/favorites')
             ->assertStatus(401);
    }

    // =========================================================================
    // TagController — GET /api/tags
    // =========================================================================

    /**
     * Requirement 10.1 — Returns all available tags.
     */
    public function test_tags_index_returns_all_tags(): void
    {
        Tag::factory()->count(5)->create();

        $response = $this->getJson('/api/tags');

        $response->assertOk();
        $this->assertCount(5, $response->json());
    }

    /**
     * Requirement 10.1 — Returns empty array when no tags exist.
     */
    public function test_tags_index_returns_empty_array_when_no_tags(): void
    {
        $response = $this->getJson('/api/tags');

        $response->assertOk()
                 ->assertExactJson([]);
    }

    /**
     * Requirement 10.1 — Tags endpoint is publicly accessible (no auth required).
     */
    public function test_tags_index_is_publicly_accessible(): void
    {
        Tag::factory()->count(3)->create();

        // No actingAs — unauthenticated request
        $this->getJson('/api/tags')->assertOk();
    }
}
