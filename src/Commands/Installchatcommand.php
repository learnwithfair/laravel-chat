<?php

namespace RahatulRabbi\LaravelChat\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallChatCommand extends Command
{
    protected $signature = 'chat:install
                            {--broadcaster=reverb : Broadcasting driver (reverb|pusher|ably)}
                            {--force : Overwrite existing published files}
                            {--no-migrate : Skip running migrations}
                            {--no-push : Skip push notification setup}';

    protected $description = 'Install and configure the Laravel Chat package';

    public function handle(): int
    {
        $this->line('');
        $this->line('  Laravel Chat Package - Installation Wizard');
        $this->line('  by Rahatul Rabbi  |  v1.0.0');
        $this->line('  ----------------------------------------');
        $this->line('');

        $this->publishConfig();
        $this->publishMigrations();
        $this->publishStubs();

        $broadcaster = $this->resolveBroadcaster();
        $this->configureBroadcaster($broadcaster);
        $this->writeEnvVariables($broadcaster);

        if (! $this->option('no-migrate')) {
            $this->runMigrations();
        }

        if (! $this->option('no-push')) {
            $this->configurePushNotifications();
        }

        $this->showPostInstallInstructions();

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->info('  [1/3] Publishing configuration...');
        Artisan::call('vendor:publish', [
            '--tag'   => 'laravel-chat-config',
            '--force' => $this->option('force'),
        ]);
        $this->line('        Published: config/laravel-chat.php');
    }

    protected function publishMigrations(): void
    {
        $this->info('  [2/3] Publishing migrations...');
        Artisan::call('vendor:publish', [
            '--tag'   => 'laravel-chat-migrations',
            '--force' => $this->option('force'),
        ]);
        $this->line('        Published: database/migrations/');
    }

    protected function publishStubs(): void
    {
        $this->info('  [3/3] Publishing stubs...');
        Artisan::call('vendor:publish', [
            '--tag'   => 'laravel-chat-stubs',
            '--force' => $this->option('force'),
        ]);
        $this->line('        Published: stubs/laravel-chat/');
    }

    protected function resolveBroadcaster(): string
    {
        $driver  = $this->option('broadcaster');
        $options = ['reverb', 'pusher', 'ably', 'log', 'null'];

        if (in_array($driver, $options, true)) {
            return $driver;
        }

        return $this->choice(
            '  Which broadcasting driver do you want to use?',
            $options,
            0
        );
    }

    protected function configureBroadcaster(string $broadcaster): void
    {
        $this->line('');
        $this->info("  Broadcaster: {$broadcaster}");

        match ($broadcaster) {
            'reverb' => $this->configureReverb(),
            'pusher' => $this->configurePusher(),
            'ably'   => $this->configureAbly(),
            default  => $this->line("  Using '{$broadcaster}' driver - no additional packages required."),
        };
    }

    protected function configureReverb(): void
    {
        if (! class_exists(\Laravel\Reverb\ReverbServiceProvider::class)) {
            $this->warn('  Laravel Reverb not found. Install it with:');
            $this->line('    composer require laravel/reverb');
            $this->line('    php artisan reverb:install');
        } else {
            $this->line('  Laravel Reverb is installed.');
        }
        $this->line('  Start server: php artisan reverb:start --debug');
    }

    protected function configurePusher(): void
    {
        $this->warn('  Pusher requires the following package:');
        $this->line('    composer require pusher/pusher-php-server');
        $this->line('  Set PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_CLUSTER in .env');
    }

    protected function configureAbly(): void
    {
        $this->warn('  Ably requires the following package:');
        $this->line('    composer require ably/ably-php');
        $this->line('  Set ABLY_KEY in .env');
    }

    protected function writeEnvVariables(string $broadcaster): void
    {
        $this->line('');
        $this->info('  Writing .env variables...');

        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            $this->warn('  .env file not found - skipping.');
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

        if (empty($added)) {
            $this->line('  All variables already present in .env');
        } else {
            foreach ($added as $key) {
                $this->line("  Added: {$key}");
            }
        }
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
        ];

        $broadcasterEnv = match ($broadcaster) {
            'reverb' => [
                'BROADCAST_DRIVER'     => 'reverb',
                'REVERB_APP_ID'        => 'laravel-chat-app',
                'REVERB_APP_KEY'       => 'laravel-chat-key',
                'REVERB_APP_SECRET'    => 'laravel-chat-secret',
                'REVERB_HOST'          => '127.0.0.1',
                'REVERB_PORT'          => '8080',
                'REVERB_SCHEME'        => 'http',
                'VITE_REVERB_APP_KEY'  => '${REVERB_APP_KEY}',
                'VITE_REVERB_HOST'     => '${REVERB_HOST}',
                'VITE_REVERB_PORT'     => '${REVERB_PORT}',
                'VITE_REVERB_SCHEME'   => '${REVERB_SCHEME}',
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
            default  => ['BROADCAST_DRIVER' => $broadcaster],
        };

        return array_merge($base, $broadcasterEnv);
    }

    protected function runMigrations(): void
    {
        $this->line('');
        if ($this->confirm('  Run database migrations now?', true)) {
            $this->info('  Running migrations...');
            Artisan::call('migrate', [], $this->output);
            $this->line('  Migrations complete.');
        }
    }

    protected function configurePushNotifications(): void
    {
        $this->line('');
        if ($this->confirm('  Enable FCM push notifications?', false)) {
            $this->setEnvValue('CHAT_PUSH_NOTIFICATIONS', 'true');
            $this->line('  Push notifications enabled.');
            $this->line('  Place Firebase service account at:');
            $this->line('    storage/app/firebase/service-account.json');
            $this->line('  Then run: php artisan vendor:publish --tag=laravel-firebase');
        }
    }

    protected function showPostInstallInstructions(): void
    {
        $this->line('');
        $this->line('  ----------------------------------------');
        $this->info('  Installation complete.');
        $this->line('  ----------------------------------------');
        $this->line('');
        $this->line('  Next steps:');
        $this->line('');
        $this->line('  1. Update user field mapping in config/laravel-chat.php:');
        $this->line('       "user_fields" => ["avatar" => "your_avatar_column"]');
        $this->line('');
        $this->line('  2. Add middleware alias to bootstrap/app.php:');
        $this->line('       $middleware->alias([');
        $this->line("         'last_seen' => \\RahatulRabbi\\LaravelChat\\Http\\Middleware\\UpdateLastSeen::class,");
        $this->line('       ]);');
        $this->line('');
        $this->line('  3. Add scheduler to bootstrap/app.php:');
        $this->line("       \$schedule->command('chat:auto-unmute')->everyMinute();");
        $this->line('');
        $this->line('  4. Start WebSocket server:');
        $this->line('       php artisan reverb:start --debug');
        $this->line('');
        $this->line('  5. Add Cron entry for scheduler:');
        $this->line('       * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1');
        $this->line('');
        $this->line('  Full documentation: https://github.com/rahatulrabbi/laravel-chat');
        $this->line('');
    }

    protected function setEnvValue(string $key, string $value): void
    {
        $envPath  = base_path('.env');
        $existing = File::get($envPath);

        if (str_contains($existing, $key . '=')) {
            File::put($envPath, preg_replace("/^{$key}=.*/m", "{$key}={$value}", $existing));
        } else {
            File::append($envPath, "\n{$key}={$value}");
        }
    }
}
