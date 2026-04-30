<?php
namespace RahatulRabbi\TalkBridge\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RahatulRabbi\TalkBridge\Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_conversations(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'meta']);
    }

    public function test_user_can_start_private_conversation(): void
    {
        $user     = $this->createUser();
        $receiver = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/conversations/private', [
            'receiver_id' => $receiver->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'private');

        $this->assertDatabaseHas('conversations', ['type' => 'private']);
    }

    public function test_user_can_create_group(): void
    {
        $user    = $this->createUser();
        $member1 = $this->createUser();
        $member2 = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/conversations', [
            'name'         => 'Test Group',
            'participants' => [$member1->id, $member2->id],
            'group'        => ['description' => 'A test group', 'type' => 'private'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'group')
            ->assertJsonPath('data.name', 'Test Group');
    }
}
