<?php

namespace Tests\Unit\Models;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $user = new User();

        $this->assertContains('name', $user->getFillable());
        $this->assertContains('email', $user->getFillable());
        $this->assertContains('password', $user->getFillable());
        $this->assertContains('avatar', $user->getFillable());
        $this->assertContains('role', $user->getFillable());
        $this->assertContains('bio', $user->getFillable());
        $this->assertContains('website', $user->getFillable());
        $this->assertContains('social_links', $user->getFillable());
    }

    /** @test */
    public function it_casts_social_links_to_array(): void
    {
        $casts = (new User())->getCasts();
        $this->assertArrayHasKey('social_links', $casts);
        $this->assertEquals('array', $casts['social_links']);
    }

    /** @test */
    public function it_hides_password_and_remember_token(): void
    {
        $hidden = (new User())->getHidden();
        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    /** @test */
    public function it_has_has_many_posts_relation(): void
    {
        $user = new User();
        $this->assertInstanceOf(HasMany::class, $user->posts());
    }

    /** @test */
    public function it_has_has_many_comments_relation(): void
    {
        $user = new User();
        $this->assertInstanceOf(HasMany::class, $user->comments());
    }

    /** @test */
    public function it_has_has_many_sent_messages_relation(): void
    {
        $user = new User();
        $relation = $user->sentMessages();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('sender_id', $relation->getForeignKeyName());
    }

    /** @test */
    public function it_has_has_many_received_messages_relation(): void
    {
        $user = new User();
        $relation = $user->receivedMessages();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals('receiver_id', $relation->getForeignKeyName());
    }

    /** @test */
    public function it_has_belongs_to_many_liked_posts_relation(): void
    {
        $user = new User();
        $relation = $user->likedPosts();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    /** @test */
    public function it_has_belongs_to_many_favorite_posts_relation(): void
    {
        $user = new User();
        $relation = $user->favoritePosts();
        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    /** @test */
    public function it_has_morph_many_notifications_relation(): void
    {
        $user = new User();
        $this->assertInstanceOf(MorphMany::class, $user->notifications());
    }

    /** @test */
    public function liked_posts_uses_post_likes_pivot_table(): void
    {
        $user = new User();
        $relation = $user->likedPosts();

        $this->assertEquals('post_likes', $relation->getTable());
    }

    /** @test */
    public function favorite_posts_uses_post_favorites_pivot_table(): void
    {
        $user = new User();
        $relation = $user->favoritePosts();

        $this->assertEquals('post_favorites', $relation->getTable());
    }
}
