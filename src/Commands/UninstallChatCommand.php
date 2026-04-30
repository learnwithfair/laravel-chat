<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RahatulRabbi\TalkBridge\Support\UserModelModifier;

class UninstallChatCommand extends Command
{
    protected $signature = 'chat:uninstall
                            {--force : Skip all confirmation prompts}
                            {--keep-data : Do not drop database tables}';

    protected $description = 'Uninstall the Laravel Chat package — removes all injected code and published files';

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

    protected array $chatEnvKeys = [
        'BROADCAST_DRIVER',
        'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET',
        'REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME',
        'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST', 'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME',
        'PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET', 'PUSHER_APP_CLUSTER',
        'VITE_PUSHER_APP_KEY', 'VITE_PUSHER_APP_CLUSTER',
        'CHAT_ONLINE_THRESHOLD', 'CHAT_ROUTE_PREFIX', 'CHAT_UPLOAD_DISK',
        'CHAT_PUSH_NOTIFICATIONS', 'CHAT_QUEUE_CONNECTION', 'CHAT_QUEUE_NAME',
        'CHAT_INVITE_URL', 'CHAT_CACHE_ENABLED', 'CHAT_CACHE_TTL', 'CHAT_MAX_FILE_SIZE',
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

    public function handle(): int
    {
        $this->printHeader();

        if (! $this->option('force')) {
            if (! $this->confirm('  This will remove all chat data, published files, and injected code. Continue?', false)) {
                $this->line('  Uninstall cancelled.');
                return self::SUCCESS;
            }
        }

        $this->step(1, 'Removing HasChatFeatures from User model');
        $this->removeUserTrait();

        if (! $this->option('keep-data')) {
            $this->step(2, 'Dropping database tables');
            $this->dropTables();
        } else {
            $this->line('  [2] Skipping table removal (--keep-data)');
        }

        $this->step(3, 'Removing published files');
        $this->removePublishedFiles();

        $this->step(4, 'Removing published migrations');
        $this->removeMigrationFiles();

        $this->step(5, 'Cleaning .env variables');
        $this->cleanEnvVariables();

        $this->printSuccess();

        return self::SUCCESS;
    }

    protected function removeUserTrait(): void
    {
        $userModelPath = $this->resolveUserModelPath();

        if (! $userModelPath) {
            $this->warn('    User model not found — skipping trait removal.');
            return;
        }

        $modifier = new UserModelModifier($userModelPath);

        if (! $modifier->isAlreadyInjected()) {
            $this->line('    HasChatFeatures not found in User model — nothing to remove.');
            return;
        }

        $modifier->remove();

        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $userModelPath);
        $this->line("    Removed HasChatFeatures  ->  {$relative}");
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
            $this->line('    Removed  ->  users.last_seen_at');
        }
    }

    protected function removePublishedFiles(): void
    {
        $targets = [
            config_path('laravel-chat.php'),
            base_path('stubs/laravel-chat'),
        ];

        foreach ($targets as $path) {
            if (File::exists($path)) {
                File::isDirectory($path)
                    ? File::deleteDirectory($path)
                    : File::delete($path);

                $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $this->line("    Removed  ->  {$relative}");
            }
        }

        // Remove vendor lang directory
        $langDir = lang_path('vendor/laravel-chat');
        if (File::exists($langDir)) {
            File::deleteDirectory($langDir);
            $this->line("    Removed  ->  lang/vendor/laravel-chat");
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
            $this->line('    No migration files found to remove.');
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

        foreach ($this->chatEnvKeys as $key) {
            if (str_contains($content, $key . '=')) {
                $content = preg_replace("/^{$key}=.*\n?/m", '', $content);
                $removed++;
            }
        }

        // Clean up multiple consecutive blank lines left behind
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($envPath, $content);

        $this->line("    Removed {$removed} variable(s) from .env");
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

    protected function printHeader(): void
    {
        $this->newLine();
        $this->line('  +--------------------------------------------------+');
        $this->warn('  |  Laravel Chat Package  -  Uninstaller             |');
        $this->line('  +--------------------------------------------------+');
        $this->newLine();
    }

    protected function printSuccess(): void
    {
        $this->newLine();
        $this->line('  +--------------------------------------------------+');
        $this->info('  |  Uninstall complete.                              |');
        $this->line('  +--------------------------------------------------+');
        $this->newLine();
        $this->line('  What was removed:');
        $this->line('    - HasChatFeatures trait from User model');
        $this->line('    - Database tables (unless --keep-data was used)');
        $this->line('    - config/laravel-chat.php');
        $this->line('    - Published migrations');
        $this->line('    - Published stubs');
        $this->line('    - .env variables');
        $this->newLine();
        $this->line('  To remove the package itself:');
        $this->line('    composer remove rahatulrabbi/talkbridge');
        $this->newLine();
    }

    protected function step(int $n, string $label): void
    {
        $this->newLine();
        $this->info("  [{$n}] {$label}");
        $this->line('  ' . str_repeat('-', 52));
    }
}
