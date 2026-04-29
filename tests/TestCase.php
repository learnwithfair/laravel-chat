<?php
namespace RahatulRabbi\LaravelChat\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RahatulRabbi\LaravelChat\LaravelChatServiceProvider;
use RahatulRabbi\LaravelChat\Models\Conversation;
use RahatulRabbi\LaravelChat\Models\ConversationParticipant;
use RahatulRabbi\LaravelChat\Models\Message;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelChatServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('laravel-chat.user_model', \App\Models\User::class);
        $app['config']->set('laravel-chat.routing.middleware', ['api']);
        $app['config']->set('broadcast.default', 'log');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function createUser(array $attributes = []): \Illuminate\Database\Eloquent\Model
    {
        $userModel = config('laravel-chat.user_model');
        return $userModel::factory()->create($attributes);
    }

    protected function createConversation(\Illuminate\Database\Eloquent\Model $user, string $type = 'private'): Conversation
    {
        $conversation = Conversation::create(['type' => $type, 'name' => $type === 'group' ? 'Test Group' : null]);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'role' => 'member', 'is_active' => true]);
        return $conversation;
    }

    protected function createMessage(Conversation $conversation, \Illuminate\Database\Eloquent\Model $sender, string $text = 'Test message'): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $sender->id,
            'message'         => $text,
            'message_type'    => 'text',
        ]);
    }
}
