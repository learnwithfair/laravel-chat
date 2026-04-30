<?php

namespace RahatulRabbi\LaravelChat;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RahatulRabbi\LaravelChat\Commands\AutoUnmuteCommand;
use RahatulRabbi\LaravelChat\Commands\InstallChatCommand;
use RahatulRabbi\LaravelChat\Commands\PublishChatCommand;
use RahatulRabbi\LaravelChat\Commands\UninstallChatCommand;
use RahatulRabbi\LaravelChat\Http\Middleware\UpdateLastSeen;

class LaravelChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-chat.php', 'laravel-chat');

        $this->app->singleton(\RahatulRabbi\LaravelChat\Services\ChatService::class);
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'laravel-chat');
        $this->registerRoutes();
        $this->registerMiddlewareAlias();
        $this->registerScheduler();
        $this->registerBroadcastChannels();
        $this->registerCommands();
    }

    // -------------------------------------------------------------------------

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

    /**
     * Register API routes automatically.
     * No manual bootstrap/app.php edit required.
     */
    protected function registerRoutes(): void
    {
        if (! config('laravel-chat.routing.enabled', true)) {
            return;
        }

        Route::prefix(config('laravel-chat.routing.prefix', 'api/v1'))
            ->middleware(config('laravel-chat.routing.middleware', ['api', 'auth:sanctum', 'laravel-chat.last-seen']))
            ->group(__DIR__ . '/../routes/api.php');
    }

    /**
     * Register the last-seen middleware alias automatically.
     * No manual bootstrap/app.php edit required.
     */
    protected function registerMiddlewareAlias(): void
    {
        $this->app['router']->aliasMiddleware('laravel-chat.last-seen', UpdateLastSeen::class);
    }

    /**
     * Register the auto-unmute scheduler automatically.
     * No manual bootstrap/app.php withSchedule() edit required.
     */
    protected function registerScheduler(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('chat:auto-unmute')->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    /**
     * Register broadcast channels automatically.
     * No manual routes/channels.php edit required.
     */
    protected function registerBroadcastChannels(): void
    {
        require_once __DIR__ . '/../routes/channels.php';
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
}
