<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\CommentMention;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $comment = new Comment();

        $this->assertContains('post_id', $comment->getFillable());
        $this->assertContains('user_id', $comment->getFillable());
        $this->assertContains('parent_id', $comment->getFillable());
        $this->assertContains('content', $comment->getFillable());
    }

    /** @test */
    public function it_has_belongs_to_post_relation(): void
    {
        $comment = new Comment();
        $this->assertInstanceOf(BelongsTo::class, $comment->post());
    }

    /** @test */
    public function it_has_belongs_to_user_relation(): void
    {
        $comment = new Comment();
        $this->assertInstanceOf(BelongsTo::class, $comment->user());
    }

    /** @test */
    public function it_has_belongs_to_parent_comment_relation(): void
    {
        $comment = new Comment();
        $relation = $comment->parent();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    /** @test */
    public function it_has_has_many_replies_relation(): void
    {
        $comment = new Comment();
        $relation = $comment->replies();
        $this->assertInstanceOf(HasMany::class, $relation);
    }

    /** @test */
    public function it_has_has_many_mentions_relation(): void
    {
        $comment = new Comment();
        $this->assertInstanceOf(HasMany::class, $comment->mentions());
    }

    /** @test */
    public function it_stores_parent_id_for_replies(): void
    {
        $user = User::factory()->create();
        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Test Post',
            'slug' => 'test-post-comment',
            'content' => 'Content',
        ]);

        $parentComment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Parent comment',
        ]);

        $reply = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $parentComment->id,
            'content' => 'A reply',
        ]);

        $this->assertEquals($parentComment->id, $reply->parent_id);
        $this->assertCount(1, $parentComment->replies);
    }

    /** @test */
    public function replies_relation_uses_parent_id_foreign_key(): void
    {
        $comment = new Comment();
        $repliesRelation = $comment->replies();

        $this->assertEquals('parent_id', $repliesRelation->getForeignKeyName());
    }

    /** @test */
    public function parent_relation_uses_parent_id_foreign_key(): void
    {
        $comment = new Comment();
        $parentRelation = $comment->parent();

        $this->assertEquals('parent_id', $parentRelation->getForeignKeyName());
    }
}
