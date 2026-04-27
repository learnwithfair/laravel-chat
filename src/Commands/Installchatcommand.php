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
        $this->displayBanner();

        // ── Step 1: Publish config ──────────────────────────────────────────
        $this->step('Publishing configuration file...');
        Artisan::call('vendor:publish', [
            '--tag'   => 'laravel-chat-config',
            '--force' => $this->option('force'),
        ]);
        $this->line('  <fg=green>✓</> Config published → <fg=cyan>config/laravel-chat.php</>');

        // ── Step 2: Publish migrations ──────────────────────────────────────
        $this->step('Publishing database migrations...');
        Artisan::call('vendor:publish', [
            '--tag'   => 'laravel-chat-migrations',
            '--force' => $this->option('force'),
        ]);
        $this->line('  <fg=green>✓</> Migrations published → <fg=cyan>database/migrations/</>');

        // ── Step 3: Publish stubs ──────────────────────────────────────────
        $this->step('Publishing stubs for customization...');
        Artisan::call('vendor:publish', [
            '--tag'   => 'laravel-chat-stubs',
            '--force' => $this->option('force'),
        ]);
        $this->line('  <fg=green>✓</> Stubs published → <fg=cyan>stubs/laravel-chat/</>');

        // ── Step 4: Configure broadcaster ─────────────────────────────────
        $broadcaster = $this->option('broadcaster');
        $broadcaster = $this->chooseBroadcaster($broadcaster);
        $this->configureBroadcaster($broadcaster);

        // ── Step 5: Configure .env ─────────────────────────────────────────
        $this->step('Writing environment variables...');
        $this->writeEnvVariables($broadcaster);

        // ── Step 6: Run migrations ─────────────────────────────────────────
        if (! $this->option('no-migrate')) {
            if ($this->confirm('  Run database migrations now?', true)) {
                $this->step('Running migrations...');
                Artisan::call('migrate', [], $this->output);
                $this->line('  <fg=green>✓</> Migrations completed');
            }
        }

        // ── Step 7: Push notification setup ───────────────────────────────
        if (! $this->option('no-push')) {
            $this->configurePushNotifications();
        }

        // ── Step 8: User model guidance ────────────────────────────────────
        $this->showUserModelInstructions();

        // ── Step 9: Schedule setup ─────────────────────────────────────────
        $this->showSchedulerInstructions();

        // ── Done ───────────────────────────────────────────────────────────
        $this->displaySuccess();

        return self::SUCCESS;
    }

    protected function chooseBroadcaster(string $default): string
    {
        $options = ['reverb', 'pusher', 'ably', 'log', 'null'];

        if (in_array($default, $options)) {
            return $default;
        }

        return $this->choice(
            '  <question> Which broadcasting driver would you like to use? </question>',
            $options,
            0
        );
    }

    protected function configureBroadcaster(string $broadcaster): void
    {
        $this->step("Configuring broadcaster: <fg=yellow>{$broadcaster}</>");

        match ($broadcaster) {
            'reverb' => $this->configureReverb(),
            'pusher' => $this->configurePusher(),
            'ably'   => $this->configureAbly(),
            default  => $this->line("  <fg=yellow>⚠</> Using '{$broadcaster}' driver — no extra config needed."),
        };
    }

    protected function configureReverb(): void
    {
        if (! class_exists(\Laravel\Reverb\ReverbServiceProvider::class)) {
            $this->line('  <fg=yellow>⚠</> Laravel Reverb not found. Installing...');
            $this->line('  Run: <fg=cyan>composer require laravel/reverb</>');
            $this->line('  Then: <fg=cyan>php artisan reverb:install</>');
        } else {
            $this->line('  <fg=green>✓</> Laravel Reverb detected.');
        }
        $this->line('  Start server: <fg=cyan>php artisan reverb:start --debug</>');
    }

    protected function configurePusher(): void
    {
        $this->line('  <fg=yellow>⚠</> Pusher requires: <fg=cyan>composer require pusher/pusher-php-server</>');
        $this->line('  Set PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_CLUSTER in .env');
    }

    protected function configureAbly(): void
    {
        $this->line('  <fg=yellow>⚠</> Ably requires: <fg=cyan>composer require ably/ably-php</>');
        $this->line('  Set ABLY_KEY in .env');
    }

    protected function writeEnvVariables(string $broadcaster): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('  .env file not found. Skipping env write.');
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

        if (! empty($added)) {
            $this->line('  <fg=green>✓</> Added to .env: ' . implode(', ', $added));
        } else {
            $this->line('  <fg=blue>ℹ</> All env variables already present.');
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
            'CHAT_INVITE_URL'         => '"${APP_URL}/api/v1/accept-invite"',
        ];

        $broadcasterEnv = match ($broadcaster) {
            'reverb' => [
                'BROADCAST_DRIVER'    => 'reverb',
                'REVERB_APP_ID'       => 'laravel-chat-app',
                'REVERB_APP_KEY'      => 'laravel-chat-key',
                'REVERB_APP_SECRET'   => 'laravel-chat-secret',
                'REVERB_HOST'         => '127.0.0.1',
                'REVERB_PORT'         => '8080',
                'REVERB_SCHEME'       => 'http',
                'VITE_REVERB_APP_KEY' => '"${REVERB_APP_KEY}"',
                'VITE_REVERB_HOST'    => '"${REVERB_HOST}"',
                'VITE_REVERB_PORT'    => '"${REVERB_PORT}"',
                'VITE_REVERB_SCHEME'  => '"${REVERB_SCHEME}"',
            ],
            'pusher' => [
                'BROADCAST_DRIVER'        => 'pusher',
                'PUSHER_APP_ID'           => '',
                'PUSHER_APP_KEY'          => '',
                'PUSHER_APP_SECRET'       => '',
                'PUSHER_APP_CLUSTER'      => 'mt1',
                'VITE_PUSHER_APP_KEY'     => '"${PUSHER_APP_KEY}"',
                'VITE_PUSHER_APP_CLUSTER' => '"${PUSHER_APP_CLUSTER}"',
            ],
            default  => ['BROADCAST_DRIVER' => $broadcaster],
        };

        return array_merge($base, $broadcasterEnv);
    }

    protected function configurePushNotifications(): void
    {
        if ($this->confirm('  Enable FCM push notifications?', false)) {
            $this->writeToEnv('CHAT_PUSH_NOTIFICATIONS', 'true');
            $this->line('  <fg=green>✓</> Push notifications enabled.');
            $this->line('  Place your Firebase service account JSON at:');
            $this->line('    <fg=cyan>storage/app/firebase/service-account.json</>');
            $this->line('  Then run: <fg=cyan>php artisan vendor:publish --tag=laravel-firebase</>');
        }
    }

    protected function showUserModelInstructions(): void
    {
        $this->newLine();
        $this->line('  <fg=yellow>◆</> <options=bold>User Model Setup</>');
        $this->line('  Open <fg=cyan>config/laravel-chat.php</> and update:');
        $this->line('');
        $this->line("  <fg=gray>'user_model'  => \\App\\Models\\User::class,</>");
        $this->line("  <fg=gray>'user_fields' => [");
        $this->line("      'avatar'    => 'avatar_path',   // your column name");
        $this->line("      'last_seen' => 'last_seen_at',  // your column name");
        $this->line("  ],</>");
        $this->newLine();
        $this->line('  Add the <fg=cyan>UpdateLastSeen</> middleware alias in <fg=cyan>bootstrap/app.php</>:');
        $this->line("  <fg=gray>\$middleware->alias(['last_seen' => \\RahatulRabbi\\LaravelChat\\Http\\Middleware\\UpdateLastSeen::class]);</>");
    }

    protected function showSchedulerInstructions(): void
    {
        $this->newLine();
        $this->line('  <fg=yellow>◆</> <options=bold>Scheduler Setup (for auto-unmute)</>');
        $this->line('  Add to your <fg=cyan>bootstrap/app.php</> withSchedule:');
        $this->line('');
        $this->line("  <fg=gray>\$schedule->command('chat:auto-unmute')->everyMinute();</>");
        $this->newLine();
        $this->line('  Or add to your Crontab:');
        $this->line('  <fg=gray>* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1</>');
    }

    protected function writeToEnv(string $key, string $value): void
    {
        $envPath  = base_path('.env');
        $existing = File::get($envPath);

        if (str_contains($existing, $key . '=')) {
            File::put($envPath, preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $existing
            ));
        } else {
            File::append($envPath, "\n{$key}={$value}");
        }
    }

    protected function displayBanner(): void
    {
        $this->newLine();
        $this->line('  <fg=blue>╔═══════════════════════════════════════════════╗</>');
        $this->line('  <fg=blue>║</>   <options=bold>🚀  Laravel Chat Package — Installer</>       <fg=blue>║</>');
        $this->line('  <fg=blue>║</>   <fg=gray>by Rahatul Rabbi  •  v1.0.0</>               <fg=blue>║</>');
        $this->line('  <fg=blue>╚═══════════════════════════════════════════════╝</>');
        $this->newLine();
    }

    protected function displaySuccess(): void
    {
        $this->newLine();
        $this->line('  <fg=green>╔══════════════════════════════════════════╗</>');
        $this->line('  <fg=green>║</>  <options=bold>✅  Installation Complete!</>             <fg=green>║</>');
        $this->line('  <fg=green>╚══════════════════════════════════════════╝</>');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('  1. Review <fg=cyan>config/laravel-chat.php</>');
        $this->line('  2. Update <fg=cyan>user_fields</> mapping');
        $this->line('  3. Run <fg=cyan>php artisan migrate</> (if skipped)');
        $this->line('  4. Start WebSocket: <fg=cyan>php artisan reverb:start</>');
        $this->line('  5. See full docs: <fg=cyan>https://github.com/rahatulrabbi/laravel-chat</>');
        $this->newLine();
    }

    protected function step(string $message): void
    {
        $this->newLine();
        $this->line("  <fg=blue>▶</> {$message}");
    }
}
