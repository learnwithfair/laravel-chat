<?php

namespace RahatulRabbi\LaravelChat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RahatulRabbi\LaravelChat\Commands\InstallChatCommand;
use RahatulRabbi\LaravelChat\Commands\UninstallChatCommand;
use RahatulRabbi\LaravelChat\Commands\PublishChatCommand;
use RahatulRabbi\LaravelChat\Commands\AutoUnmuteCommand;

class LaravelChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-chat.php',
            'laravel-chat'
        );

        $this->app->singleton(
            \RahatulRabbi\LaravelChat\Services\ChatService::class
        );
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->loadMigrations();
        $this->loadRoutes();
        $this->loadTranslations();
        $this->registerCommands();
        $this->registerChannels();
        $this->registerMiddlewareAlias();
        $this->registerScheduler();
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-chat.php' => config_path('laravel-chat.php'),
        ], 'laravel-chat-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'laravel-chat-migrations');

        $this->publishes([
            __DIR__ . '/../stubs/' => base_path('stubs/laravel-chat'),
        ], 'laravel-chat-stubs');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/laravel-chat'),
        ], 'laravel-chat-lang');

        // Publish everything at once
        $this->publishes([
            __DIR__ . '/../config/laravel-chat.php' => config_path('laravel-chat.php'),
            __DIR__ . '/../database/migrations/'    => database_path('migrations'),
            __DIR__ . '/../stubs/'                  => base_path('stubs/laravel-chat'),
            __DIR__ . '/../lang'                    => lang_path('vendor/laravel-chat'),
        ], 'laravel-chat');
    }

    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function loadRoutes(): void
    {
        if (! config('laravel-chat.routing.enabled', true)) {
            return;
        }

        Route::prefix(config('laravel-chat.routing.prefix', 'api/v1'))
            ->middleware(config('laravel-chat.routing.middleware', ['api', 'auth:sanctum']))
            ->group(__DIR__ . '/../routes/api.php');
    }

    protected function loadTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'laravel-chat');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallChatCommand::class,
                UninstallChatCommand::class,
                PublishChatCommand::class,
                AutoUnmuteCommand::class,
            ]);
        }
    }

    protected function registerChannels(): void
    {
        require_once __DIR__ . '/../routes/channels.php';
    }

    protected function registerMiddlewareAlias(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware(
            'laravel-chat.last-seen',
            \RahatulRabbi\LaravelChat\Http\Middleware\UpdateLastSeen::class
        );
    }

    protected function registerScheduler(): void
    {
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command('chat:auto-unmute')->everyMinute();
        });
    }
}
