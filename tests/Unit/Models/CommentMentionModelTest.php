<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\CommentMention;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentMentionModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $mention = new CommentMention();

        $this->assertContains('comment_id', $mention->getFillable());
        $this->assertContains('mentioned_user_id', $mention->getFillable());
    }

    /** @test */
    public function it_has_belongs_to_comment_relation(): void
    {
        $mention = new CommentMention();
        $this->assertInstanceOf(BelongsTo::class, $mention->comment());
    }

    /** @test */
    public function it_has_belongs_to_mentioned_user_relation(): void
    {
        $mention = new CommentMention();
        $relation = $mention->mentionedUser();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    /** @test */
    public function mentioned_user_relation_uses_mentioned_user_id_foreign_key(): void
    {
        $mention = new CommentMention();
        $this->assertEquals('mentioned_user_id', $mention->mentionedUser()->getForeignKeyName());
    }

    /** @test */
    public function it_can_be_created_and_linked_to_comment_and_user(): void
    {
        $author = User::factory()->create();
        $mentioned = User::factory()->create();
        $post = Post::create([
            'user_id' => $author->id,
            'title' => 'Test Post',
            'slug' => 'test-post-mention',
            'content' => 'Content',
        ]);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => '@' . $mentioned->name . ' check this out!',
        ]);

        $mention = CommentMention::create([
            'comment_id' => $comment->id,
            'mentioned_user_id' => $mentioned->id,
        ]);

        $this->assertEquals($comment->id, $mention->comment->id);
        $this->assertEquals($mentioned->id, $mention->mentionedUser->id);
    }
}
