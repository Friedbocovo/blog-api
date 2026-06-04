<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $post = new Post();

        $this->assertContains('user_id', $post->getFillable());
        $this->assertContains('title', $post->getFillable());
        $this->assertContains('slug', $post->getFillable());
        $this->assertContains('content', $post->getFillable());
        $this->assertContains('status', $post->getFillable());
        $this->assertContains('pinned', $post->getFillable());
    }

    /** @test */
    public function it_casts_pinned_to_boolean(): void
    {
        $post = new Post();
        $casts = $post->getCasts();

        $this->assertArrayHasKey('pinned', $casts);
        $this->assertEquals('boolean', $casts['pinned']);
    }

    /** @test */
    public function it_casts_published_at_to_datetime(): void
    {
        $post = new Post();
        $casts = $post->getCasts();

        $this->assertArrayHasKey('published_at', $casts);
        $this->assertEquals('datetime', $casts['published_at']);
    }

    /** @test */
    public function it_has_belongs_to_user_relation(): void
    {
        $post = new Post();
        $this->assertInstanceOf(BelongsTo::class, $post->user());
    }

    /** @test */
    public function it_has_has_many_comments_relation(): void
    {
        $post = new Post();
        $this->assertInstanceOf(HasMany::class, $post->comments());
    }

    /** @test */
    public function it_has_belongs_to_many_tags_relation(): void
    {
        $post = new Post();
        $relation = $post->tags();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    /** @test */
    public function it_has_belongs_to_many_liked_by_users_relation(): void
    {
        $post = new Post();
        $relation = $post->likedByUsers();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    /** @test */
    public function it_has_belongs_to_many_favorited_by_users_relation(): void
    {
        $post = new Post();
        $relation = $post->favoritedByUsers();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    /** @test */
    public function it_generates_slug_from_title_on_creating(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'My First Blog Post',
            'content' => 'Some content here.',
        ]);

        $this->assertEquals('my-first-blog-post', $post->slug);
    }

    /** @test */
    public function it_generates_unique_slug_with_numeric_suffix_on_collision(): void
    {
        $user = User::factory()->create();

        $post1 = Post::create([
            'user_id' => $user->id,
            'title' => 'Hello World',
            'content' => 'Content 1',
        ]);

        $post2 = Post::create([
            'user_id' => $user->id,
            'title' => 'Hello World',
            'content' => 'Content 2',
        ]);

        $post3 = Post::create([
            'user_id' => $user->id,
            'title' => 'Hello World',
            'content' => 'Content 3',
        ]);

        $this->assertEquals('hello-world', $post1->slug);
        $this->assertEquals('hello-world-1', $post2->slug);
        $this->assertEquals('hello-world-2', $post3->slug);
    }

    /** @test */
    public function it_does_not_overwrite_explicitly_provided_slug(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Some Title',
            'content' => 'Content',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $post->slug);
    }

    /** @test */
    public function generate_unique_slug_returns_slug_without_suffix_when_no_collision(): void
    {
        $slug = Post::generateUniqueSlug('Hello World');
        $this->assertEquals('hello-world', $slug);
    }

    /** @test */
    public function generate_unique_slug_appends_counter_on_existing_slug(): void
    {
        $user = User::factory()->create();
        Post::create([
            'user_id' => $user->id,
            'title' => 'Existing Title',
            'slug' => 'existing-title',
            'content' => 'Content',
        ]);

        $slug = Post::generateUniqueSlug('Existing Title');
        $this->assertEquals('existing-title-1', $slug);
    }

    /** @test */
    public function pinned_attribute_is_cast_to_boolean_when_reading(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Test Post',
            'slug' => 'test-post-pinned',
            'content' => 'Content',
            'pinned' => true,
        ]);

        $fresh = Post::find($post->id);
        $this->assertIsBool($fresh->pinned);
        $this->assertTrue($fresh->pinned);
    }
}
