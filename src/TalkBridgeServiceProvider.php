<?php

namespace RahatulRabbi\TalkBridge;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RahatulRabbi\TalkBridge\Commands\AutoUnmuteCommand;
use RahatulRabbi\TalkBridge\Commands\InstallCommand;
use RahatulRabbi\TalkBridge\Commands\PublishCommand;
use RahatulRabbi\TalkBridge\Commands\UninstallCommand;
use RahatulRabbi\TalkBridge\Http\Middleware\UpdateLastSeen;

class TalkBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/talkbridge.php', 'talkbridge');

        $this->app->singleton(\RahatulRabbi\TalkBridge\Services\ChatService::class);
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'talkbridge');
        $this->registerRoutes();
        $this->registerMiddlewareAlias();
        $this->registerScheduler();
        $this->registerBroadcastChannels();
        $this->registerCommands();
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/talkbridge.php' => config_path('talkbridge.php'),
        ], 'talkbridge-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'talkbridge-migrations');

        $this->publishes([
            __DIR__ . '/../stubs/' => base_path('stubs/talkbridge'),
        ], 'talkbridge-stubs');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/talkbridge'),
        ], 'talkbridge-lang');

        $this->publishes([
            __DIR__ . '/../config/talkbridge.php' => config_path('talkbridge.php'),
            __DIR__ . '/../database/migrations/'  => database_path('migrations'),
            __DIR__ . '/../stubs/'                => base_path('stubs/talkbridge'),
            __DIR__ . '/../lang'                  => lang_path('vendor/talkbridge'),
        ], 'talkbridge');
    }

    /**
     * Register API routes automatically — no bootstrap/app.php edit required.
     */
    protected function registerRoutes(): void
    {
        if (! config('talkbridge.routing.enabled', true)) {
            return;
        }

        Route::prefix(config('talkbridge.routing.prefix', 'api/v1'))
            ->middleware(config('talkbridge.routing.middleware', ['api', 'auth:sanctum', 'talkbridge.last-seen']))
            ->group(__DIR__ . '/../routes/api.php');
    }

    /**
     * Register middleware alias automatically — no bootstrap/app.php edit required.
     */
    protected function registerMiddlewareAlias(): void
    {
        $this->app['router']->aliasMiddleware('talkbridge.last-seen', UpdateLastSeen::class);
    }

    /**
     * Register scheduler automatically — no bootstrap/app.php withSchedule() required.
     */
    protected function registerScheduler(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('talkbridge:auto-unmute')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    /**
     * Register broadcast channels automatically — no routes/channels.php edit required.
     */
    protected function registerBroadcastChannels(): void
    {
        require_once __DIR__ . '/../routes/channels.php';
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                UninstallCommand::class,
                PublishCommand::class,
                AutoUnmuteCommand::class,
            ]);
        }
    }
}
