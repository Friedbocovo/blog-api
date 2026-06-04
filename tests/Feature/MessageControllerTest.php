<?php

namespace Tests\Feature;

use App\Events\NewMessage;
use App\Events\NewNotification;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Feature tests for MessageController and ConversationController.
 *
 * Validates: Requirements 6
 */
class MessageControllerTest extends TestCase
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

    // =========================================================================
    // GET /api/conversations
    // =========================================================================

    /**
     * Requirement 6.1 — Conversations list returns interlocutors with last message.
     */
    public function test_conversations_index_returns_list_with_last_message(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        // Create a message from visitor to admin (older)
        Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'content'     => 'First message',
            'created_at'  => now()->subMinutes(10),
            'updated_at'  => now()->subMinutes(10),
        ]);

        // Create a more recent message from admin to visitor
        $lastMsg = Message::factory()->create([
            'sender_id'   => $admin->id,
            'receiver_id' => $visitor->id,
            'content'     => 'Last message',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/conversations');

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data);

        $conversation = $data[0];
        $this->assertArrayHasKey('user', $conversation);
        $this->assertArrayHasKey('last_message', $conversation);
        $this->assertArrayHasKey('unread_count', $conversation);

        $this->assertEquals($visitor->id, $conversation['user']['id']);
        $this->assertEquals($lastMsg->id, $conversation['last_message']['id']);
        $this->assertEquals('Last message', $conversation['last_message']['content']);
    }

    /**
     * Requirement 6.1 — Unread count only counts messages sent BY the interlocutor to auth user.
     */
    public function test_conversations_index_includes_correct_unread_count(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        // Two unread messages from visitor to admin
        Message::factory()->count(2)->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'read_at'     => null,
        ]);

        // One read message from visitor to admin
        Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'read_at'     => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/conversations');

        $response->assertOk();

        $conversation = $response->json(0);
        $this->assertEquals(2, $conversation['unread_count']);
    }

    /**
     * Requirement 6.6 — GET /api/conversations requires authentication.
     */
    public function test_conversations_index_returns_401_without_auth(): void
    {
        $this->getJson('/api/conversations')
            ->assertStatus(401);
    }

    // =========================================================================
    // GET /api/conversations/{userId}
    // =========================================================================

    /**
     * Requirement 6.2 — Returns all messages between two users ordered by created_at ASC.
     */
    public function test_conversations_show_returns_messages_ordered_asc(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $firstMsg = Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'content'     => 'First',
            'created_at'  => now()->subMinutes(5),
        ]);

        $secondMsg = Message::factory()->create([
            'sender_id'   => $admin->id,
            'receiver_id' => $visitor->id,
            'content'     => 'Second',
            'created_at'  => now()->subMinutes(2),
        ]);

        $thirdMsg = Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'content'     => 'Third',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/conversations/{$visitor->id}");

        $response->assertOk();

        $messages = $response->json();
        $this->assertCount(3, $messages);

        // Verify ascending order
        $this->assertEquals($firstMsg->id, $messages[0]['id']);
        $this->assertEquals($secondMsg->id, $messages[1]['id']);
        $this->assertEquals($thirdMsg->id, $messages[2]['id']);
    }

    /**
     * Requirement 6.2 — Messages include sender and receiver relations.
     */
    public function test_conversations_show_loads_sender_and_receiver(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/conversations/{$visitor->id}");

        $response->assertOk();

        $firstMessage = $response->json(0);
        $this->assertArrayHasKey('sender', $firstMessage);
        $this->assertArrayHasKey('receiver', $firstMessage);
        $this->assertEquals($visitor->id, $firstMessage['sender']['id']);
        $this->assertEquals($admin->id, $firstMessage['receiver']['id']);
    }

    /**
     * Requirement 6.6 — GET /api/conversations/{userId} requires authentication.
     */
    public function test_conversations_show_returns_401_without_auth(): void
    {
        $visitor = $this->createVisitor();

        $this->getJson("/api/conversations/{$visitor->id}")
            ->assertStatus(401);
    }

    // =========================================================================
    // POST /api/messages
    // =========================================================================

    /**
     * Requirement 6.3 — Admin can send a message to a visitor → 201.
     */
    public function test_store_admin_to_visitor_returns_201(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $visitor->id,
                'content'     => 'Hello visitor!',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['content' => 'Hello visitor!']);

        $this->assertDatabaseHas('messages', [
            'sender_id'   => $admin->id,
            'receiver_id' => $visitor->id,
            'content'     => 'Hello visitor!',
        ]);
    }

    /**
     * Requirement 6.3 — Visitor can send a message to admin → 201.
     */
    public function test_store_visitor_to_admin_returns_201(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $response = $this->actingAs($visitor, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $admin->id,
                'content'     => 'Hello admin!',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['content' => 'Hello admin!']);

        $this->assertDatabaseHas('messages', [
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'content'     => 'Hello admin!',
        ]);
    }

    /**
     * Requirement 6.5 — Visitor sending to another visitor returns 422.
     */
    public function test_store_visitor_to_visitor_returns_422(): void
    {
        Event::fake();

        $sender   = $this->createVisitor();
        $receiver = $this->createVisitor();

        $response = $this->actingAs($sender, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $receiver->id,
                'content'     => 'Forbidden message',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('messages', [
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
        ]);
    }

    /**
     * Requirement 6.5 — Admin sending to another admin returns 422.
     */
    public function test_store_admin_to_admin_returns_422(): void
    {
        Event::fake();

        $admin1 = $this->createAdmin();
        $admin2 = $this->createAdmin();

        $response = $this->actingAs($admin1, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $admin2->id,
                'content'     => 'Admin to admin',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Requirement 6.6 — POST /api/messages requires authentication.
     */
    public function test_store_returns_401_without_auth(): void
    {
        $visitor = $this->createVisitor();

        $this->postJson('/api/messages', [
            'receiver_id' => $visitor->id,
            'content'     => 'Hello!',
        ])->assertStatus(401);
    }

    /**
     * Requirement 6.3 — Missing content field returns 422 validation error.
     */
    public function test_store_returns_422_when_content_missing(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $visitor->id,
            ])->assertStatus(422);
    }

    /**
     * Requirement 6.3 — Missing receiver_id field returns 422 validation error.
     */
    public function test_store_returns_422_when_receiver_id_missing(): void
    {
        Event::fake();

        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/messages', [
                'content' => 'Hello!',
            ])->assertStatus(422);
    }

    // =========================================================================
    // PATCH /api/messages/{id}/read
    // =========================================================================

    /**
     * Requirement 6.4 — Mark a message as read sets read_at.
     */
    public function test_mark_read_sets_read_at_timestamp(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $message = Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
            'read_at'     => null,
        ]);

        $this->assertNull($message->read_at);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/messages/{$message->id}/read");

        $response->assertOk();

        $this->assertNotNull($response->json('read_at'));

        $this->assertDatabaseMissing('messages', [
            'id'      => $message->id,
            'read_at' => null,
        ]);
    }

    /**
     * Requirement 6.4 — Returns 404 for a non-existent message.
     */
    public function test_mark_read_returns_404_for_nonexistent_message(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/messages/99999/read')
            ->assertStatus(404);
    }

    /**
     * Requirement 6.6 — PATCH /api/messages/{id}/read requires authentication.
     */
    public function test_mark_read_returns_401_without_auth(): void
    {
        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $message = Message::factory()->create([
            'sender_id'   => $visitor->id,
            'receiver_id' => $admin->id,
        ]);

        $this->patchJson("/api/messages/{$message->id}/read")
            ->assertStatus(401);
    }

    // =========================================================================
    // Events
    // =========================================================================

    /**
     * Requirement 6.3, 11.1 — NewMessage event is dispatched when a message is created.
     *
     * Property 3 relates to role constraint; this test verifies the event dispatch.
     */
    public function test_store_dispatches_new_message_event(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $visitor->id,
                'content'     => 'Event test message',
            ])->assertStatus(201);

        Event::assertDispatched(NewMessage::class, function (NewMessage $event) use ($visitor) {
            return $event->message->receiver_id === $visitor->id
                && $event->message->content === 'Event test message';
        });
    }

    /**
     * Requirement 7.4 — NewNotification event is dispatched to the receiver.
     */
    public function test_store_dispatches_new_notification_event_to_receiver(): void
    {
        Event::fake();

        $admin   = $this->createAdmin();
        $visitor = $this->createVisitor();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $visitor->id,
                'content'     => 'Notification test',
            ])->assertStatus(201);

        Event::assertDispatched(NewNotification::class, function (NewNotification $event) use ($visitor) {
            return $event->userId === $visitor->id;
        });
    }

    /**
     * Requirement 6.5, Property 3 — NewMessage event is NOT dispatched for same-role messages.
     */
    public function test_store_does_not_dispatch_events_for_same_role_message(): void
    {
        Event::fake();

        $visitor1 = $this->createVisitor();
        $visitor2 = $this->createVisitor();

        $this->actingAs($visitor1, 'sanctum')
            ->postJson('/api/messages', [
                'receiver_id' => $visitor2->id,
                'content'     => 'Should not be sent',
            ])->assertStatus(422);

        Event::assertNotDispatched(NewMessage::class);
    }
}
