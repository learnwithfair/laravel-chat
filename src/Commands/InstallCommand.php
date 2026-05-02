<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RahatulRabbi\TalkBridge\Support\ComposerRunner;
use RahatulRabbi\TalkBridge\Support\UserModelModifier;

class InstallCommand extends Command
{
    protected $signature = 'talkbridge:install
                            {--broadcaster=reverb : Broadcasting driver (reverb|pusher|ably)}
                            {--push=none : Push notification provider (none|fcm|web|both)}
                            {--force : Overwrite existing published files}
                            {--no-migrate : Skip running migrations}';

    protected $description = 'Install TalkBridge — fully automatic, zero manual steps';

    protected ComposerRunner $composer;

    public function handle(): int
    {
        $this->composer = new ComposerRunner();

        $this->printHeader();

        $this->step(1, 'Publishing assets');
        $this->publishAssets();

        $this->step(2, 'Selecting broadcaster');
        $broadcaster = $this->resolveBroadcaster();

        $this->step(3, 'Installing broadcaster package');
        $this->installBroadcaster($broadcaster);

        $this->step(4, 'Writing broadcaster .env variables');
        $this->writeBroadcasterEnv($broadcaster);

        $this->step(5, 'Selecting push notification provider');
        $pushProvider = $this->resolvePushProvider();

        $this->step(6, 'Installing push notification package');
        $this->installPushProvider($pushProvider);

        $this->step(7, 'Writing push notification .env variables');
        $this->writePushEnv($pushProvider);

        $this->step(8, 'Writing base .env variables');
        $this->writeBaseEnv($broadcaster);

        $this->step(9, 'Patching User model');
        $this->patchUserModel();

        $this->step(10, 'ServiceProvider auto-wiring');
        $this->printAutoWiringSummary();

        if (! $this->option('no-migrate')) {
            $this->step(11, 'Running migrations');
            $this->runMigrations();
        }

        $this->printSuccess($broadcaster, $pushProvider);

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Step implementations
    // -------------------------------------------------------------------------

    protected function publishAssets(): void
    {
        $tags = [
            'talkbridge-config'     => 'config/talkbridge.php',
            'talkbridge-migrations' => 'database/migrations/',
            'talkbridge-stubs'      => 'stubs/talkbridge/',
        ];

        foreach ($tags as $tag => $dest) {
            Artisan::call('vendor:publish', [
                '--tag'   => $tag,
                '--force' => $this->option('force'),
            ]);
            $this->line("    Published  ->  {$dest}");
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

    protected function installBroadcaster(string $broadcaster): void
    {
        match ($broadcaster) {
            'reverb' => $this->installReverb(),
            'pusher' => $this->installPusher(),
            'ably'   => $this->installAbly(),
            default  => $this->line("    No extra package needed for '{$broadcaster}'."),
        };
    }

    protected function installReverb(): void
    {
        if ($this->composer->isInstalled(\Laravel\Reverb\ReverbServiceProvider::class)) {
            $this->line('    laravel/reverb already installed.');
            return;
        }

        $this->line('    Installing laravel/reverb...');
        [$ok, $out] = $this->composer->require('laravel/reverb');

        if ($ok) {
            $this->line('    laravel/reverb installed.');
            Artisan::call('reverb:install', ['--no-interaction' => true]);
            $this->line('    reverb:install complete.');
        } else {
            $this->warn("    composer require laravel/reverb failed:\n{$out}");
        }
    }

    protected function installPusher(): void
    {
        if ($this->composer->isInstalled(\Pusher\Pusher::class)) {
            $this->line('    pusher/pusher-php-server already installed.');
            return;
        }

        $this->line('    Installing pusher/pusher-php-server...');
        [$ok, $out] = $this->composer->require('pusher/pusher-php-server');

        $ok
            ? $this->line('    pusher/pusher-php-server installed.')
            : $this->warn("    Install failed:\n{$out}");
    }

    protected function installAbly(): void
    {
        if ($this->composer->isInstalled(\Ably\AblyRest::class)) {
            $this->line('    ably/ably-php already installed.');
            return;
        }

        $this->line('    Installing ably/ably-php...');
        [$ok, $out] = $this->composer->require('ably/ably-php');

        $ok
            ? $this->line('    ably/ably-php installed.')
            : $this->warn("    Install failed:\n{$out}");
    }

    protected function resolvePushProvider(): string
    {
        $provider = $this->option('push');
        $options  = ['none', 'fcm', 'web', 'both'];

        if (in_array($provider, $options, true)) {
            return $provider;
        }

        return $this->choice(
            '    Push notification provider?',
            [
                'none — disabled',
                'fcm  — Firebase (Android + iOS)',
                'web  — Browser Web Push (VAPID)',
                'both — FCM + Web Push',
            ],
            0
        );
    }

    protected function installPushProvider(string $provider): void
    {
        if ($provider === 'none') {
            $this->line('    Push notifications disabled.');
            return;
        }

        if (in_array($provider, ['fcm', 'both'], true)) {
            $this->installFcm();
        }

        if (in_array($provider, ['web', 'both'], true)) {
            $this->installWebPush();
        }
    }

    protected function installFcm(): void
    {
        if ($this->composer->isInstalled(\Kreait\Firebase\Factory::class)) {
            $this->line('    kreait/laravel-firebase already installed.');
        } else {
            $this->line('    Installing kreait/laravel-firebase...');
            [$ok, $out] = $this->composer->require('kreait/laravel-firebase');

            if ($ok) {
                $this->line('    kreait/laravel-firebase installed.');
                Artisan::call('vendor:publish', ['--tag' => 'laravel-firebase']);
            } else {
                $this->warn("    Install failed:\n{$out}");
                return;
            }
        }

        $this->line('    Place Firebase credentials at:');
        $this->line('      storage/app/firebase/service-account.json');
    }

    protected function installWebPush(): void
    {
        if ($this->composer->isInstalled(\Minishlink\WebPush\WebPush::class)) {
            $this->line('    minishlink/web-push already installed.');
        } else {
            $this->line('    Installing minishlink/web-push...');
            [$ok, $out] = $this->composer->require('minishlink/web-push');

            $ok
                ? $this->line('    minishlink/web-push installed.')
                : $this->warn("    Install failed:\n{$out}");
        }

        $this->line('    Generate VAPID keys with:');
        $this->line('      php artisan talkbridge:generate-vapid');
    }

    protected function writeBroadcasterEnv(string $broadcaster): void
    {
        $vars = match ($broadcaster) {
            'reverb' => [
                'BROADCAST_DRIVER'    => 'reverb',
                'REVERB_APP_ID'       => 'talkbridge-app',
                'REVERB_APP_KEY'      => 'talkbridge-key',
                'REVERB_APP_SECRET'   => 'talkbridge-secret',
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
            'ably' => [
                'BROADCAST_DRIVER' => 'ably',
                'ABLY_KEY'         => '',
            ],
            default => ['BROADCAST_DRIVER' => $broadcaster],
        };

        $this->appendEnvVars($vars);
    }

    protected function writePushEnv(string $provider): void
    {
        $vars = ['TALKBRIDGE_PUSH_PROVIDER' => $provider];

        if (in_array($provider, ['web', 'both'], true)) {
            $vars['VAPID_PUBLIC_KEY']  = '';
            $vars['VAPID_PRIVATE_KEY'] = '';
            $vars['VAPID_SUBJECT']     = 'mailto:admin@example.com';
        }

        $this->appendEnvVars($vars);
    }

    protected function writeBaseEnv(string $broadcaster): void
    {
        $this->appendEnvVars([
            'TALKBRIDGE_ONLINE_THRESHOLD' => '2',
            'TALKBRIDGE_ROUTE_PREFIX'     => 'api/v1',
            'TALKBRIDGE_UPLOAD_DISK'      => 'public',
            'TALKBRIDGE_QUEUE_CONNECTION' => 'sync',
            'TALKBRIDGE_QUEUE_NAME'       => 'talkbridge',
            'TALKBRIDGE_CACHE_ENABLED'    => 'true',
            'TALKBRIDGE_CACHE_TTL'        => '300',
            'TALKBRIDGE_INVITE_URL'       => '${APP_URL}/api/v1/accept-invite',
        ]);
    }

    protected function patchUserModel(): void
    {
        $path = $this->resolveUserModelPath();

        if (! $path) {
            $this->warn('    User model not found. Add trait manually:');
            $this->warn('    use \\RahatulRabbi\\TalkBridge\\Traits\\HasTalkBridgeFeatures;');
            return;
        }

        $modifier = new UserModelModifier($path);
        $modifier->inject();

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->line("    Patched  ->  {$relative}");
        $this->line('    - HasTalkBridgeFeatures trait injected');
        $this->line('    - last_seen_at added to fillable (if applicable)');
    }

    protected function printAutoWiringSummary(): void
    {
        $this->line('    All registered automatically via TalkBridgeServiceProvider:');
        $this->line('      - Middleware alias "talkbridge.last-seen"');
        $this->line('      - Scheduler: talkbridge:auto-unmute every minute');
        $this->line('      - Broadcast channels: online / user.{id} / conversation.{id}');
        $this->line('      - API routes under: ' . config('talkbridge.routing.prefix', 'api/v1'));
        $this->line('    No bootstrap/app.php edits required.');
    }

    protected function runMigrations(): void
    {
        if ($this->confirm('    Run migrations now?', true)) {
            Artisan::call('migrate', [], $this->output);
            $this->line('    Migrations complete.');
        }
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    protected function appendEnvVars(array $vars): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('    .env not found — skipping.');
            return;
        }

        $existing = File::get($envPath);

        foreach ($vars as $key => $value) {
            if (! str_contains($existing, $key . '=')) {
                File::append($envPath, "\n{$key}={$value}");
                $this->line("    Added .env  ->  {$key}");
            }
        }
    }

    protected function resolveUserModelPath(): ?string
    {
        $userModel    = config('talkbridge.user_model', 'App\\Models\\User');
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

    // -------------------------------------------------------------------------
    // Output helpers
    // -------------------------------------------------------------------------

    protected function printHeader(): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->line('  |   TalkBridge  -  Real-time Chat for Laravel         |');
        $this->line('  |   by MD. RAHATUL RABBI  |  v1.0.0                   |');
        $this->line('  |   github.com/learnwithfair/talkbridge               |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
    }

    protected function printSuccess(string $broadcaster, string $pushProvider): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->info('  |   Installation complete. Zero manual steps taken.  |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
        $this->line("  Broadcaster:       {$broadcaster}");
        $this->line("  Push provider:     {$pushProvider}");
        $this->newLine();
        $this->line('  Everything configured automatically:');
        $this->line('    - config/talkbridge.php');
        $this->line('    - Migrations published and run');
        $this->line('    - HasTalkBridgeFeatures injected into User model');
        $this->line('    - Middleware, scheduler, channels, routes auto-registered');
        $this->newLine();
        $this->line('  Recommended next steps:');
        $this->line('    1. Review config/talkbridge.php (check user_fields mapping)');

        if ($broadcaster === 'reverb') {
            $this->line('    2. php artisan reverb:start --debug');
        } elseif ($broadcaster === 'pusher') {
            $this->line('    2. Fill PUSHER_* values in .env');
        }

        if (in_array($pushProvider, ['fcm', 'both'])) {
            $this->line('    3. Add storage/app/firebase/service-account.json');
        }

        if (in_array($pushProvider, ['web', 'both'])) {
            $this->line('    3. php artisan talkbridge:generate-vapid');
        }

        $this->line('    4. php artisan queue:work --queue=talkbridge');
        $this->newLine();
        $this->line('  To uninstall: php artisan talkbridge:uninstall');
        $this->line('  Docs: https://github.com/learnwithfair/talkbridge');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 54));
    }
}
