<?php

namespace Tests\Feature;

use App\Events\PostUpdated;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for PostController (public) and Admin\PostController.
 *
 * Validates: Requirements 2, 3
 */
class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Public — GET /api/posts
    // =========================================================================

    /**
     * Requirement 3.1 — Only published posts are returned.
     */
    public function test_index_returns_only_published_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Post::factory()->count(3)->create(['user_id' => $admin->id, 'status' => 'published']);
        Post::factory()->count(2)->create(['user_id' => $admin->id, 'status' => 'draft']);

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Requirement 3.1, 17.1 — Pinned posts appear before non-pinned posts.
     */
    public function test_index_returns_pinned_posts_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create non-pinned posts first (older)
        Post::factory()->count(2)->create([
            'user_id'      => $admin->id,
            'status'       => 'published',
            'pinned'       => false,
            'published_at' => now()->subDays(5),
        ]);

        // Create a pinned post after (newer slug-wise but must be listed first)
        Post::factory()->create([
            'user_id'      => $admin->id,
            'status'       => 'published',
            'pinned'       => true,
            'published_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('/api/posts');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertTrue((bool) $data[0]['pinned'], 'First post should be pinned');
    }

    /**
     * Requirement 3.4 — Filter by tag slug.
     */
    public function test_index_filters_by_tag(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tag   = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $taggedPost   = Post::factory()->create(['user_id' => $admin->id, 'status' => 'published']);
        $untaggedPost = Post::factory()->create(['user_id' => $admin->id, 'status' => 'published']);

        $taggedPost->tags()->attach($tag);

        $response = $this->getJson('/api/posts?tag=laravel');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($taggedPost->id, $data[0]['id']);
    }

    /**
     * Requirement 3.5 — Search by title or excerpt.
     */
    public function test_index_filters_by_search_term(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Post::factory()->create([
            'user_id' => $admin->id,
            'status'  => 'published',
            'title'   => 'A post about Laravel testing',
            'excerpt' => 'Some excerpt',
        ]);
        Post::factory()->create([
            'user_id' => $admin->id,
            'status'  => 'published',
            'title'   => 'Unrelated title',
            'excerpt' => 'Unrelated excerpt',
        ]);

        $response = $this->getJson('/api/posts?search=Laravel');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Laravel', $data[0]['title']);
    }

    // =========================================================================
    // Public — GET /api/posts/pinned
    // =========================================================================

    /**
     * Requirement 3.2 — Only published pinned posts are returned.
     */
    public function test_pinned_returns_only_published_pinned_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Post::factory()->create(['user_id' => $admin->id, 'status' => 'published', 'pinned' => true]);
        Post::factory()->create(['user_id' => $admin->id, 'status' => 'published', 'pinned' => false]);
        Post::factory()->create(['user_id' => $admin->id, 'status' => 'draft',     'pinned' => true]);

        $response = $this->getJson('/api/posts/pinned');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertTrue((bool) $data[0]['pinned']);
        $this->assertEquals('published', $data[0]['status']);
    }

    // =========================================================================
    // Public — GET /api/posts/{slug}
    // =========================================================================

    /**
     * Requirement 3.6 — Draft post returns 404.
     */
    public function test_show_returns_404_for_draft_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id, 'status' => 'draft']);

        $this->getJson("/api/posts/{$post->slug}")->assertStatus(404);
    }

    /**
     * Requirement 3.6 — Non-existent slug returns 404.
     */
    public function test_show_returns_404_for_nonexistent_slug(): void
    {
        $this->getJson('/api/posts/this-slug-does-not-exist')->assertStatus(404);
    }

    /**
     * Requirement 3.3 — views_count is incremented on each visit.
     */
    public function test_show_increments_views_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create([
            'user_id'     => $admin->id,
            'status'      => 'published',
            'views_count' => 5,
        ]);

        $this->getJson("/api/posts/{$post->slug}")->assertOk();

        $this->assertDatabaseHas('posts', [
            'id'          => $post->id,
            'views_count' => 6,
        ]);
    }

    /**
     * Requirement 3.3 — Published post returns its data.
     */
    public function test_show_returns_published_post(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id, 'status' => 'published']);

        $response = $this->getJson("/api/posts/{$post->slug}");

        $response->assertOk()
                 ->assertJsonPath('id', $post->id)
                 ->assertJsonPath('slug', $post->slug);
    }

    // =========================================================================
    // Admin — GET /api/admin/posts
    // =========================================================================

    /**
     * Requirement 2.7 — Admin can list all posts including drafts.
     */
    public function test_admin_index_returns_all_posts_including_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Post::factory()->count(2)->create(['user_id' => $admin->id, 'status' => 'published']);
        Post::factory()->count(3)->create(['user_id' => $admin->id, 'status' => 'draft']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->getJson('/api/admin/posts');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * Requirement 20.9 — Admin route returns 403 for visitors.
     */
    public function test_admin_index_returns_403_for_visitor(): void
    {
        $visitor = User::factory()->create(['role' => 'visitor']);

        $this->actingAs($visitor, 'sanctum')
             ->getJson('/api/admin/posts')
             ->assertStatus(403);
    }

    // =========================================================================
    // Admin — POST /api/admin/posts (store)
    // =========================================================================

    /**
     * Requirement 2.1 — Admin can create a post and get 201 back.
     */
    public function test_admin_store_creates_post_and_returns_201(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/posts', [
                             'title'   => 'My New Post',
                             'content' => 'Post content here',
                             'status'  => 'draft',
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('title', 'My New Post')
                 ->assertJsonPath('status', 'draft');

        $this->assertDatabaseHas('posts', ['title' => 'My New Post']);
    }

    /**
     * Requirement 2.2 — Slug is auto-generated from the title.
     */
    public function test_admin_store_auto_generates_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/posts', [
                             'title'   => 'Hello World Post',
                             'content' => 'Content',
                         ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('slug'));
        $this->assertStringContainsString('hello-world-post', $response->json('slug'));
    }

    /**
     * Requirement 2.1 — Admin can create a post with tags.
     */
    public function test_admin_store_associates_tags(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/posts', [
                             'title'   => 'Tagged Post',
                             'content' => 'Content',
                             'tags'    => ['PHP', 'Laravel'],
                         ]);

        $response->assertStatus(201);
        $post = Post::find($response->json('id'));
        $this->assertCount(2, $post->tags);
    }

    /**
     * Requirement 2.10 — Cover image must be a valid image, max 5MB.
     */
    public function test_admin_store_validates_cover_image_type(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/posts', [
                             'title'        => 'Post with invalid image',
                             'content'      => 'Content',
                             'cover_image'  => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                         ]);

        $response->assertStatus(422);
    }

    /**
     * Requirement 2.10 — Valid image upload is accepted.
     */
    public function test_admin_store_accepts_valid_cover_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/admin/posts', [
                             'title'       => 'Post with image',
                             'content'     => 'Content',
                             'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
                         ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('cover_image'));
    }

    /**
     * Requirement 2.1 — Title is required.
     */
    public function test_admin_store_returns_422_when_title_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
             ->postJson('/api/admin/posts', ['content' => 'Content'])
             ->assertStatus(422);
    }

    // =========================================================================
    // Admin — PUT /api/admin/posts/{id} (update)
    // =========================================================================

    /**
     * Requirement 2.3 — Admin can update a post.
     */
    public function test_admin_update_updates_post_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson("/api/admin/posts/{$post->id}", [
                             'title' => 'Updated Title',
                         ]);

        $response->assertOk()
                 ->assertJsonPath('title', 'Updated Title');

        $this->assertDatabaseHas('posts', [
            'id'    => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Requirement 2.3 — Admin can sync tags on update.
     */
    public function test_admin_update_syncs_tags(): void
    {
        $admin     = User::factory()->create(['role' => 'admin']);
        $post      = Post::factory()->create(['user_id' => $admin->id]);
        $oldTag    = Tag::factory()->create();
        $post->tags()->attach($oldTag);

        $this->actingAs($admin, 'sanctum')
             ->putJson("/api/admin/posts/{$post->id}", [
                 'tags' => ['NewTag'],
             ]);

        $post->refresh();
        $this->assertCount(1, $post->tags);
        $this->assertEquals('newtag', $post->tags->first()->slug);
    }

    // =========================================================================
    // Admin — DELETE /api/admin/posts/{id} (destroy)
    // =========================================================================

    /**
     * Requirement 2.4 — Admin can delete a post; returns 204.
     */
    public function test_admin_destroy_deletes_post_and_returns_204(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->deleteJson("/api/admin/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    // =========================================================================
    // Admin — PATCH /api/admin/posts/{id}/publish
    // =========================================================================

    /**
     * Requirement 2.5 — Toggle publish changes status draft→published.
     */
    public function test_admin_publish_toggles_draft_to_published(): void
    {
        Event::fake([PostUpdated::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->draft()->create(['user_id' => $admin->id]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->patchJson("/api/admin/posts/{$post->id}/publish");

        $response->assertOk()
                 ->assertJsonPath('status', 'published');

        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => 'published',
        ]);

        Event::assertDispatched(PostUpdated::class);
    }

    /**
     * Requirement 2.5 — Toggle publish changes status published→draft.
     */
    public function test_admin_publish_toggles_published_to_draft(): void
    {
        Event::fake([PostUpdated::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id, 'status' => 'published']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->patchJson("/api/admin/posts/{$post->id}/publish");

        $response->assertOk()
                 ->assertJsonPath('status', 'draft');

        Event::assertDispatched(PostUpdated::class);
    }

    /**
     * Requirement 2.5 — published_at is set when a draft is published.
     */
    public function test_admin_publish_sets_published_at_when_publishing(): void
    {
        Event::fake([PostUpdated::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->draft()->create(['user_id' => $admin->id]);

        $this->assertNull($post->published_at);

        $this->actingAs($admin, 'sanctum')
             ->patchJson("/api/admin/posts/{$post->id}/publish");

        $this->assertNotNull($post->fresh()->published_at);
    }

    // =========================================================================
    // Admin — PATCH /api/admin/posts/{id}/pin
    // =========================================================================

    /**
     * Requirement 2.6 — Toggle pin changes pinned state.
     */
    public function test_admin_pin_toggles_pinned_state(): void
    {
        Event::fake([PostUpdated::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id, 'pinned' => false]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->patchJson("/api/admin/posts/{$post->id}/pin");

        $response->assertOk()
                 ->assertJsonPath('pinned', true);

        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'pinned' => true,
        ]);

        Event::assertDispatched(PostUpdated::class);
    }

    /**
     * Requirement 2.6 — Toggle pin from true → false.
     */
    public function test_admin_pin_toggles_pinned_from_true_to_false(): void
    {
        Event::fake([PostUpdated::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $post  = Post::factory()->create(['user_id' => $admin->id, 'pinned' => true]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->patchJson("/api/admin/posts/{$post->id}/pin");

        $response->assertOk()
                 ->assertJsonPath('pinned', false);
    }

    // =========================================================================
    // Admin — GET /api/admin/posts/stats
    // =========================================================================

    /**
     * Requirement 2.8 — Stats endpoint returns correct counts.
     */
    public function test_admin_stats_returns_correct_counts(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $visitor = User::factory()->create(['role' => 'visitor']);

        // Create posts
        $published1 = Post::factory()->create([
            'user_id'     => $admin->id,
            'status'      => 'published',
            'views_count' => 10,
        ]);
        $published2 = Post::factory()->create([
            'user_id'     => $admin->id,
            'status'      => 'published',
            'views_count' => 5,
        ]);
        Post::factory()->draft()->create([
            'user_id'     => $admin->id,
            'views_count' => 3,
        ]);

        // Add a like from visitor to published1
        \Illuminate\Support\Facades\DB::table('post_likes')->insert([
            'user_id'    => $visitor->id,
            'post_id'    => $published1->id,
            'created_at' => now(),
        ]);

        // Add a comment
        \App\Models\Comment::factory()->create([
            'post_id' => $published1->id,
            'user_id' => $visitor->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->getJson('/api/admin/posts/stats');

        $response->assertOk()
                 ->assertJsonPath('total_posts', 3)
                 ->assertJsonPath('published_posts', 2)
                 ->assertJsonPath('draft_posts', 1)
                 ->assertJsonPath('total_views', 18)
                 ->assertJsonPath('total_likes', 1)
                 ->assertJsonPath('total_comments', 1);
    }
}
