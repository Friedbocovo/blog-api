<?php

namespace Tests\Unit\Models;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_fillable_attributes(): void
    {
        $message = new Message();

        $this->assertContains('sender_id', $message->getFillable());
        $this->assertContains('receiver_id', $message->getFillable());
        $this->assertContains('content', $message->getFillable());
        $this->assertContains('read_at', $message->getFillable());
    }

    /** @test */
    public function it_casts_read_at_to_datetime(): void
    {
        $message = new Message();
        $casts = $message->getCasts();

        $this->assertArrayHasKey('read_at', $casts);
        $this->assertEquals('datetime', $casts['read_at']);
    }

    /** @test */
    public function it_has_belongs_to_sender_relation(): void
    {
        $message = new Message();
        $relation = $message->sender();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    /** @test */
    public function it_has_belongs_to_receiver_relation(): void
    {
        $message = new Message();
        $relation = $message->receiver();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    /** @test */
    public function sender_relation_uses_sender_id_foreign_key(): void
    {
        $message = new Message();
        $this->assertEquals('sender_id', $message->sender()->getForeignKeyName());
    }

    /** @test */
    public function receiver_relation_uses_receiver_id_foreign_key(): void
    {
        $message = new Message();
        $this->assertEquals('receiver_id', $message->receiver()->getForeignKeyName());
    }

    /** @test */
    public function read_at_is_null_by_default(): void
    {
        $sender = User::factory()->create(['role' => 'admin']);
        $receiver = User::factory()->create(['role' => 'visitor']);

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Hello!',
        ]);

        $this->assertNull($message->read_at);
    }

    /** @test */
    public function read_at_is_cast_to_carbon_instance_when_set(): void
    {
        $sender = User::factory()->create(['role' => 'admin']);
        $receiver = User::factory()->create(['role' => 'visitor']);

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Hello!',
            'read_at' => now(),
        ]);

        $fresh = Message::find($message->id);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->read_at);
    }
}
