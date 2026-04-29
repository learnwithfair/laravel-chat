<?php

namespace RahatulRabbi\LaravelChat\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class UninstallChatCommand extends Command
{
    protected $signature = 'chat:uninstall
                            {--force : Skip confirmation prompts}
                            {--keep-data : Skip rolling back migrations (preserve database tables)}';

    protected $description = 'Uninstall the Laravel Chat package and clean up published files';

    protected array $publishedFiles = [
        'config/laravel-chat.php',
        'stubs/laravel-chat',
    ];

    protected array $chatEnvKeys = [
        'BROADCAST_DRIVER',
        'REVERB_APP_ID',
        'REVERB_APP_KEY',
        'REVERB_APP_SECRET',
        'REVERB_HOST',
        'REVERB_PORT',
        'REVERB_SCHEME',
        'VITE_REVERB_APP_KEY',
        'VITE_REVERB_HOST',
        'VITE_REVERB_PORT',
        'VITE_REVERB_SCHEME',
        'PUSHER_APP_ID',
        'PUSHER_APP_KEY',
        'PUSHER_APP_SECRET',
        'PUSHER_APP_CLUSTER',
        'VITE_PUSHER_APP_KEY',
        'VITE_PUSHER_APP_CLUSTER',
        'CHAT_ONLINE_THRESHOLD',
        'CHAT_ROUTE_PREFIX',
        'CHAT_UPLOAD_DISK',
        'CHAT_PUSH_NOTIFICATIONS',
        'CHAT_QUEUE_CONNECTION',
        'CHAT_QUEUE_NAME',
        'CHAT_INVITE_URL',
        'CHAT_CACHE_ENABLED',
        'CHAT_CACHE_TTL',
        'CHAT_MAX_FILE_SIZE',
    ];

    public function handle(): int
    {
        $this->line('');
        $this->warn('  Laravel Chat Package - Uninstaller');
        $this->line('  ----------------------------------------');
        $this->line('');

        if (! $this->option('force')) {
            if (! $this->confirm('  This will remove all published chat files. Continue?', false)) {
                $this->line('  Uninstall cancelled.');
                return self::SUCCESS;
            }
        }

        if (! $this->option('keep-data')) {
            $this->rollbackMigrations();
        }

        $this->removePublishedFiles();
        $this->removeMigrationFiles();
        $this->cleanEnvVariables();

        $this->line('');
        $this->line('  ----------------------------------------');
        $this->info('  Uninstall complete.');
        $this->line('  ----------------------------------------');
        $this->line('');
        $this->line('  Remove the package itself with:');
        $this->line('    composer remove rahatulrabbi/laravel-chat');
        $this->line('');

        return self::SUCCESS;
    }

    protected function rollbackMigrations(): void
    {
        $chatTables = [
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

        $this->info('  Rolling back chat tables...');

        foreach ($chatTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->line("  Dropped: {$table}");
            }
        }

        // Remove last_seen_at column from users if it exists
        if (Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', function ($table) {
                $table->dropColumn('last_seen_at');
            });
            $this->line('  Removed: users.last_seen_at');
        }
    }

    protected function removePublishedFiles(): void
    {
        $this->info('  Removing published files...');

        foreach ($this->publishedFiles as $path) {
            $full = base_path($path);
            if (File::exists($full)) {
                File::isDirectory($full) ? File::deleteDirectory($full) : File::delete($full);
                $this->line("  Removed: {$path}");
            }
        }
    }

    protected function removeMigrationFiles(): void
    {
        $this->info('  Removing published migration files...');

        $patterns = [
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

        $files = File::files(database_path('migrations'));

        foreach ($files as $file) {
            foreach ($patterns as $pattern) {
                if (str_contains($file->getFilename(), $pattern)) {
                    File::delete($file->getPathname());
                    $this->line("  Removed: database/migrations/{$file->getFilename()}");
                    break;
                }
            }
        }
    }

    protected function cleanEnvVariables(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $this->info('  Cleaning .env variables...');
        $content = File::get($envPath);
        $removed = 0;

        foreach ($this->chatEnvKeys as $key) {
            if (str_contains($content, $key . '=')) {
                $content = preg_replace("/^{$key}=.*\n?/m", '', $content);
                $removed++;
            }
        }

        File::put($envPath, $content);
        $this->line("  Removed {$removed} env variable(s).");
    }
}
