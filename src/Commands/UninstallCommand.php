<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RahatulRabbi\TalkBridge\Support\ComposerRunner;
use RahatulRabbi\TalkBridge\Support\UserModelModifier;

class UninstallCommand extends Command
{
    protected $signature = 'talkbridge:uninstall
                            {--force : Skip all confirmation prompts}
                            {--keep-data : Do not drop database tables}
                            {--keep-packages : Do not remove installed optional packages}';

    protected $description = 'Uninstall TalkBridge — removes all injected code, files, and optional packages';

    protected ComposerRunner $composer;

    protected array $chatTables = [
        'conversation_invites',
        'message_deletions',
        'message_reactions',
        'message_statuses',
        'message_attachments',
        'messages',
        'group_settings',
        'conversation_participants',
        'conversations',
        'device_tokens',
        'user_restricts',
        'user_blocks',
    ];

    protected array $envKeys = [
        'BROADCAST_DRIVER',
        'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET',
        'REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME',
        'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST', 'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME',
        'PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'PUSHER_APP_CLUSTER',
        'VITE_PUSHER_APP_KEY', 'VITE_PUSHER_APP_CLUSTER',
        'ABLY_KEY',
        'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY', 'VAPID_SUBJECT',
        'TALKBRIDGE_ONLINE_THRESHOLD', 'TALKBRIDGE_ROUTE_PREFIX',
        'TALKBRIDGE_UPLOAD_DISK', 'TALKBRIDGE_QUEUE_CONNECTION',
        'TALKBRIDGE_QUEUE_NAME', 'TALKBRIDGE_CACHE_ENABLED',
        'TALKBRIDGE_CACHE_TTL', 'TALKBRIDGE_INVITE_URL',
        'TALKBRIDGE_PUSH_PROVIDER', 'TALKBRIDGE_MAX_FILE_SIZE',
        'FIREBASE_CREDENTIALS',
    ];

    protected array $migrationPatterns = [
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
        'add_last_seen_at_to_users_table',
    ];

    // Optional packages installed by talkbridge:install
    protected array $optionalPackages = [
        'laravel/reverb'             => \Laravel\Reverb\ReverbServiceProvider::class,
        'pusher/pusher-php-server'   => \Pusher\Pusher::class,
        'ably/ably-php'              => \Ably\AblyRest::class,
        'kreait/laravel-firebase'    => \Kreait\Firebase\Factory::class,
        'minishlink/web-push'        => \Minishlink\WebPush\WebPush::class,
    ];

    public function handle(): int
    {
        $this->composer = new ComposerRunner();

        $this->printHeader();

        if (! $this->option('force')) {
            if (! $this->confirm('  This removes all TalkBridge data, files, and injected code. Continue?', false)) {
                $this->line('  Uninstall cancelled.');
                return self::SUCCESS;
            }
        }

        $this->step(1, 'Restoring User model');
        $this->restoreUserModel();

        if (! $this->option('keep-data')) {
            $this->step(2, 'Dropping database tables');
            $this->dropTables();
        } else {
            $this->line('  [2] Skipping table removal (--keep-data flag set)');
        }

        $this->step(3, 'Removing published files');
        $this->removePublishedFiles();

        $this->step(4, 'Removing published migrations');
        $this->removeMigrationFiles();

        $this->step(5, 'Cleaning .env variables');
        $this->cleanEnvVariables();

        if (! $this->option('keep-packages')) {
            $this->step(6, 'Removing optional packages');
            $this->removeOptionalPackages();
        } else {
            $this->line('  [6] Skipping package removal (--keep-packages flag set)');
        }

        $this->printSuccess();

        return self::SUCCESS;
    }

    protected function restoreUserModel(): void
    {
        $path = $this->resolveUserModelPath();

        if (! $path) {
            $this->warn('    User model not found — skipping.');
            return;
        }

        $modifier = new UserModelModifier($path);

        if (! $modifier->isAlreadyInjected()) {
            $this->line('    HasTalkBridgeFeatures not found — nothing to remove.');
            return;
        }

        $modifier->remove();

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        $this->line("    Restored  ->  {$relative}");
        $this->line('    - HasTalkBridgeFeatures trait removed');
        $this->line('    - last_seen_at removed from fillable (if added)');
    }

    protected function dropTables(): void
    {
        foreach ($this->chatTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->line("    Dropped  ->  {$table}");
            }
        }

        if (Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', fn($t) => $t->dropColumn('last_seen_at'));
            $this->line('    Removed column  ->  users.last_seen_at');
        }
    }

    protected function removePublishedFiles(): void
    {
        $targets = [
            config_path('talkbridge.php'),
            base_path('stubs/talkbridge'),
            lang_path('vendor/talkbridge'),
        ];

        foreach ($targets as $path) {
            if (File::exists($path)) {
                File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
                $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $this->line("    Removed  ->  {$relative}");
            }
        }
    }

    protected function removeMigrationFiles(): void
    {
        $files   = File::files(database_path('migrations'));
        $removed = 0;

        foreach ($files as $file) {
            foreach ($this->migrationPatterns as $pattern) {
                if (str_contains($file->getFilename(), $pattern)) {
                    File::delete($file->getPathname());
                    $this->line("    Removed  ->  database/migrations/{$file->getFilename()}");
                    $removed++;
                    break;
                }
            }
        }

        if ($removed === 0) {
            $this->line('    No TalkBridge migration files found.');
        }
    }

    protected function cleanEnvVariables(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('    .env not found — skipping.');
            return;
        }

        $content = File::get($envPath);
        $removed = 0;

        foreach ($this->envKeys as $key) {
            if (str_contains($content, $key . '=')) {
                $content = preg_replace("/^{$key}=.*\n?/m", '', $content);
                $removed++;
            }
        }

        // Clean up extra blank lines
        $content = preg_replace("/\n{3,}/", "\n\n", $content);
        File::put($envPath, $content);

        $this->line("    Removed {$removed} variable(s) from .env");
    }

    protected function removeOptionalPackages(): void
    {
        foreach ($this->optionalPackages as $package => $checkClass) {
            if (! $this->composer->isInstalled($checkClass)) {
                continue;
            }

            if ($this->option('force') || $this->confirm("    Remove {$package}?", false)) {
                $this->line("    Removing {$package}...");
                [$ok, $out] = $this->composer->remove($package);

                $ok
                    ? $this->line("    Removed  ->  {$package}")
                    : $this->warn("    Failed to remove {$package}:\n{$out}");
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

    protected function printHeader(): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->warn('  |   TalkBridge  -  Uninstaller                       |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
    }

    protected function printSuccess(): void
    {
        $this->newLine();
        $this->line('  +----------------------------------------------------+');
        $this->info('  |   Uninstall complete.                              |');
        $this->line('  +----------------------------------------------------+');
        $this->newLine();
        $this->line('  Removed:');
        $this->line('    - HasTalkBridgeFeatures from User model');
        $this->line('    - Database tables (unless --keep-data)');
        $this->line('    - config/talkbridge.php');
        $this->line('    - Published migrations and stubs');
        $this->line('    - .env variables');
        $this->line('    - Optional packages (unless --keep-packages)');
        $this->newLine();
        $this->line('  To remove TalkBridge itself:');
        $this->line('    composer remove rahatulrabbi/talkbridge');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 54));
    }
}
