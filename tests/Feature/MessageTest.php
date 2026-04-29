<?php
namespace RahatulRabbi\LaravelChat\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RahatulRabbi\LaravelChat\Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_message(): void
    {
        $user         = $this->createUser();
        $conversation = $this->createConversation($user);

        $response = $this->actingAs($user)->postJson('/api/v1/messages', [
            'conversation_id' => $conversation->id,
            'message'         => 'Hello from test',
            'message_type'    => 'text',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.message', 'Hello from test');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'message'         => 'Hello from test',
            'sender_id'       => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $response = $this->postJson('/api/v1/messages', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_delete_message_for_themselves(): void
    {
        $user         = $this->createUser();
        $conversation = $this->createConversation($user);
        $message      = $this->createMessage($conversation, $user);

        $response = $this->actingAs($user)->deleteJson('/api/v1/messages/delete-for-me', [
            'message_ids' => [$message->id],
        ]);

        $response->assertStatus(200);
    }

    public function test_user_can_delete_own_message_for_everyone(): void
    {
        $user         = $this->createUser();
        $conversation = $this->createConversation($user);
        $message      = $this->createMessage($conversation, $user);

        $response = $this->actingAs($user)->deleteJson('/api/v1/messages/delete-for-everyone', [
            'message_ids' => [$message->id],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('messages', [
            'id'                      => $message->id,
            'is_deleted_for_everyone' => true,
        ]);
    }
}
