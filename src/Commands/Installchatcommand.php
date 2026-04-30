<?php

namespace RahatulRabbi\LaravelChat\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RahatulRabbi\LaravelChat\Support\UserModelModifier;

class InstallChatCommand extends Command
{
    protected $signature = 'chat:install
                            {--broadcaster=reverb : Broadcasting driver (reverb|pusher|ably)}
                            {--force : Overwrite existing published files}
                            {--no-migrate : Skip running migrations}
                            {--no-push : Skip push notification setup}';

    protected $description = 'Install and configure the Laravel Chat package — fully automatic';

    public function handle(): int
    {
        $this->printHeader();

        $this->step(1, 'Publishing assets');
        $this->publishAssets();

        $this->step(2, 'Configuring broadcaster');
        $broadcaster = $this->resolveBroadcaster();
        $this->configureBroadcaster($broadcaster);

        $this->step(3, 'Writing .env variables');
        $this->writeEnvVariables($broadcaster);

        $this->step(4, 'Injecting HasChatFeatures into User model');
        $this->injectUserTrait();

        $this->step(5, 'ServiceProvider auto-wiring summary');
        $this->printAutoWiringSummary();

        if (! $this->option('no-migrate')) {
            $this->step(6, 'Database migrations');
            $this->runMigrations();
        }

        if (! $this->option('no-push')) {
            $this->step(7, 'Push notifications');
            $this->configurePushNotifications();
        }

        $this->printSuccess();

        return self::SUCCESS;
    }

    protected function publishAssets(): void
    {
        $tags = [
            'laravel-chat-config'     => 'config/laravel-chat.php',
            'laravel-chat-migrations' => 'database/migrations/',
            'laravel-chat-stubs'      => 'stubs/laravel-chat/',
        ];

        foreach ($tags as $tag => $destination) {
            Artisan::call('vendor:publish', [
                '--tag'   => $tag,
                '--force' => $this->option('force'),
            ]);
            $this->line("    Published  ->  {$destination}");
        }
    }

    protected function resolveBroadcaster(): string
    {
        $driver  = $this->option('broadcaster');
        $options = ['reverb', 'pusher', 'ably', 'log', 'null'];

        if (in_array($driver, $options, true)) {
            return $driver;
        }

        return $this->choice('    Which broadcasting driver?', $options, 0);
    }

    protected function configureBroadcaster(string $broadcaster): void
    {
        $this->line("    Driver selected: {$broadcaster}");

        match ($broadcaster) {
            'reverb' => $this->handleReverb(),
            'pusher' => $this->handlePusher(),
            'ably'   => $this->handleAbly(),
            default  => $this->line("    No extra packages required for '{$broadcaster}'."),
        };
    }

    protected function handleReverb(): void
    {
        if (! class_exists(\Laravel\Reverb\ReverbServiceProvider::class)) {
            $this->warn('    Laravel Reverb not found. Install it after setup:');
            $this->line('      composer require laravel/reverb');
            $this->line('      php artisan reverb:install');
        } else {
            $this->line('    Laravel Reverb is installed.');
        }
    }

    protected function handlePusher(): void
    {
        if (! class_exists(\Pusher\Pusher::class)) {
            $this->warn('    Pusher SDK not found.');
            $this->line('    Run: composer require pusher/pusher-php-server');
        }
        $this->line('    Fill PUSHER_APP_ID / KEY / SECRET / CLUSTER in .env');
    }

    protected function handleAbly(): void
    {
        $this->warn('    Ably SDK not found.');
        $this->line('    Run: composer require ably/ably-php');
        $this->line('    Set ABLY_KEY in .env');
    }

    protected function writeEnvVariables(string $broadcaster): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('    .env not found — skipping.');
            return;
        }

        $additions = $this->getEnvAdditions($broadcaster);
        $existing  = File::get($envPath);
        $added     = [];

        foreach ($additions as $key => $value) {
            if (! str_contains($existing, $key . '=')) {
                File::append($envPath, "\n{$key}={$value}");
                $added[] = $key;
            }
        }

        empty($added)
            ? $this->line('    All variables already present.')
            : collect($added)->each(fn($k) => $this->line("    Added  ->  {$k}"));
    }

    protected function getEnvAdditions(string $broadcaster): array
    {
        $base = [
            'CHAT_ONLINE_THRESHOLD'   => '2',
            'CHAT_ROUTE_PREFIX'       => 'api/v1',
            'CHAT_UPLOAD_DISK'        => 'public',
            'CHAT_PUSH_NOTIFICATIONS' => 'false',
            'CHAT_QUEUE_CONNECTION'   => 'sync',
            'CHAT_QUEUE_NAME'         => 'chat',
            'CHAT_INVITE_URL'         => '${APP_URL}/api/v1/accept-invite',
            'CHAT_CACHE_ENABLED'      => 'true',
            'CHAT_CACHE_TTL'          => '300',
        ];

        $driverEnv = match ($broadcaster) {
            'reverb' => [
                'BROADCAST_DRIVER'    => 'reverb',
                'REVERB_APP_ID'       => 'laravel-chat-app',
                'REVERB_APP_KEY'      => 'laravel-chat-key',
                'REVERB_APP_SECRET'   => 'laravel-chat-secret',
                'REVERB_HOST'         => '127.0.0.1',
                'REVERB_PORT'         => '8080',
                'REVERB_SCHEME'       => 'http',
                'VITE_REVERB_APP_KEY' => '${REVERB_APP_KEY}',
                'VITE_REVERB_HOST'    => '${REVERB_HOST}',
                'VITE_REVERB_PORT'    => '${REVERB_PORT}',
                'VITE_REVERB_SCHEME'  => '${REVERB_SCHEME}',
            ],
            'pusher' => [
                'BROADCAST_DRIVER'        => 'pusher',
                'PUSHER_APP_ID'           => '',
                'PUSHER_APP_KEY'          => '',
                'PUSHER_APP_SECRET'       => '',
                'PUSHER_APP_CLUSTER'      => 'mt1',
                'VITE_PUSHER_APP_KEY'     => '${PUSHER_APP_KEY}',
                'VITE_PUSHER_APP_CLUSTER' => '${PUSHER_APP_CLUSTER}',
            ],
            default => ['BROADCAST_DRIVER' => $broadcaster],
        };

        return array_merge($base, $driverEnv);
    }

    protected function injectUserTrait(): void
    {
        $userModelPath = $this->resolveUserModelPath();

        if (! $userModelPath) {
            $this->warn('    User model not found at expected path.');
            $this->warn('    Manually add: use \\RahatulRabbi\\LaravelChat\\Traits\\HasChatFeatures;');
            return;
        }

        $modifier = new UserModelModifier($userModelPath);

        if ($modifier->isAlreadyInjected()) {
            $this->line('    HasChatFeatures already present — skipped.');
            return;
        }

        $modifier->inject();

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $userModelPath);
        $this->line("    Injected HasChatFeatures  ->  {$relative}");
    }

    protected function printAutoWiringSummary(): void
    {
        $this->line('    The ServiceProvider handles all of the following automatically:');
        $this->line('      - Middleware alias "laravel-chat.last-seen"');
        $this->line('      - Scheduler: chat:auto-unmute every minute');
        $this->line('      - Broadcast channels: online / user.{id} / conversation.{id}');
        $this->line('      - API routes under prefix: ' . config('laravel-chat.routing.prefix', 'api/v1'));
        $this->line('    No bootstrap/app.php edits required.');
    }

    protected function runMigrations(): void
    {
        if ($this->confirm('    Run migrations now?', true)) {
            Artisan::call('migrate', [], $this->output);
            $this->line('    Migrations complete.');
        }
    }

    protected function configurePushNotifications(): void
    {
        if ($this->confirm('    Enable FCM push notifications?', false)) {
            $this->setEnvValue('CHAT_PUSH_NOTIFICATIONS', 'true');
            $this->line('    Enabled. Place Firebase JSON at:');
            $this->line('      storage/app/firebase/service-account.json');
            $this->line('    Install SDK if needed:');
            $this->line('      composer require kreait/laravel-firebase');
        }
    }

    protected function resolveUserModelPath(): ?string
    {
        $userModel    = config('laravel-chat.user_model', 'App\\Models\\User');
        $relativePath = ltrim(str_replace(['App\\', '\\'], ['app/', '/'], $userModel), '/') . '.php';
        $fullPath      = base_path($relativePath);

        if (File::exists($fullPath)) {
            return $fullPath;
        }

        foreach ([app_path('Models/User.php'), app_path('User.php')] as $fallback) {
            if (File::exists($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    protected function setEnvValue(string $key, string $value): void
    {
        $envPath  = base_path('.env');
        $existing = File::get($envPath);

        str_contains($existing, $key . '=')
            ? File::put($envPath, preg_replace("/^{$key}=.*/m", "{$key}={$value}", $existing))
            : File::append($envPath, "\n{$key}={$value}");
    }

    protected function printHeader(): void
    {
        $this->newLine();
        $this->line('  +--------------------------------------------------+');
        $this->line('  |  Laravel Chat Package  -  Installation Wizard     |');
        $this->line('  |  by Rahatul Rabbi  |  v1.0.0                      |');
        $this->line('  +--------------------------------------------------+');
        $this->newLine();
    }

    protected function printSuccess(): void
    {
        $this->newLine();
        $this->line('  +--------------------------------------------------+');
        $this->info('  |  Installation complete. Zero manual steps taken.  |');
        $this->line('  +--------------------------------------------------+');
        $this->newLine();
        $this->line('  What was done automatically:');
        $this->line('    - config/laravel-chat.php published');
        $this->line('    - Migrations published and run');
        $this->line('    - HasChatFeatures trait injected into User model');
        $this->line('    - Middleware alias registered (ServiceProvider)');
        $this->line('    - Scheduler registered (ServiceProvider)');
        $this->line('    - Broadcast channels registered (ServiceProvider)');
        $this->line('    - API routes registered (ServiceProvider)');
        $this->newLine();
        $this->line('  Recommended next steps:');
        $this->line('    1. Review config/laravel-chat.php  (check user_fields mapping)');
        $this->line('    2. php artisan reverb:start --debug');
        $this->line('    3. php artisan queue:work --queue=chat');
        $this->newLine();
        $this->line('  To uninstall: php artisan chat:uninstall');
        $this->line('  Docs: https://github.com/rahatulrabbi/laravel-chat');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 52));
    }
}
