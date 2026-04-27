<?php
namespace RahatulRabbi\LaravelChat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RahatulRabbi\LaravelChat\Commands\InstallChatCommand;
use RahatulRabbi\LaravelChat\Commands\PublishChatCommand;
use RahatulRabbi\LaravelChat\Commands\UninstallChatCommand;

class LaravelChatServiceProvider extends ServiceProvider
{
    /**
     * All package migrations in publish order.
     */
    protected array $migrations = [
        'create_conversations_table',
        'create_conversation_participants_table',
        'create_messages_table',
        'create_message_attachments_table',
        'create_message_reactions_table',
        'create_message_statuses_table',
        'create_message_deletions_table',
        'create_group_settings_table',
        'create_conversation_invites_table',
        'create_device_tokens_table',
        'create_user_blocks_table',
        'create_user_restricts_table',
    ];

    /**
     * Register package bindings.
     */
    public function register(): void
    {
        // Merge default config (user config overrides defaults)
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-chat.php',
            'laravel-chat'
        );

        // Bind the ChatService as singleton
        $this->app->singleton(
            \RahatulRabbi\LaravelChat\Services\ChatService::class
        );
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->loadMigrations();
        $this->loadRoutes();
        $this->loadTranslations();
        $this->registerCommands();
        $this->registerChannels();
    }

    /**
     * Register all publishable assets.
     */
    protected function registerPublishables(): void
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/laravel-chat.php' => config_path('laravel-chat.php'),
        ], 'laravel-chat-config');

        // Migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-chat-migrations');

        // Stubs (for customization)
        $this->publishes([
            __DIR__ . '/../stubs/' => base_path('stubs/laravel-chat'),
        ], 'laravel-chat-stubs');

        // Channel routes (broadcasting)
        $this->publishes([
            __DIR__ . '/../stubs/channels.stub' => base_path('stubs/laravel-chat/channels.php'),
        ], 'laravel-chat-channels');

        // All assets at once
        $this->publishes([
            __DIR__ . '/../config/laravel-chat.php' => config_path('laravel-chat.php'),
            __DIR__ . '/../database/migrations/'    => database_path('migrations'),
            __DIR__ . '/../stubs/'                  => base_path('stubs/laravel-chat'),
        ], 'laravel-chat');
    }

    /**
     * Load migrations (without requiring publish).
     */
    protected function loadMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Register package API routes.
     */
    protected function loadRoutes(): void
    {
        $config = config('laravel-chat.routing', []);

        if (config('laravel-chat.routing.enabled', true)) {
            Route::prefix(config('laravel-chat.routing.prefix', 'api/v1'))
                ->middleware(config('laravel-chat.routing.middleware', ['api', 'auth:sanctum']))
                ->group(__DIR__ . '/../routes/api.php');
        }
    }

    /**
     * Load translations.
     */
    protected function loadTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'laravel-chat');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/laravel-chat'),
        ], 'laravel-chat-lang');
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallChatCommand::class,
                UninstallChatCommand::class,
                PublishChatCommand::class,
            ]);
        }
    }

    /**
     * Register broadcast channels from package.
     */
    protected function registerChannels(): void
    {
        require_once __DIR__ . '/../routes/channels.php';
    }
}
