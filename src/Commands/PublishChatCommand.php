<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishChatCommand extends Command
{
    protected $signature = 'chat:publish
                            {--tag= : Specific tag to publish (config|migrations|stubs|lang)}
                            {--force : Overwrite existing files}';

    protected $description = 'Publish specific Laravel Chat package assets';

    protected array $availableTags = [
        'config'     => 'laravel-chat-config',
        'migrations' => 'laravel-chat-migrations',
        'stubs'      => 'laravel-chat-stubs',
        'lang'       => 'laravel-chat-lang',
        'all'        => 'laravel-chat',
    ];

    public function handle(): int
    {
        $tag = $this->option('tag');

        if ($tag && ! array_key_exists($tag, $this->availableTags)) {
            $this->error("Unknown tag: {$tag}");
            $this->line('Available tags: ' . implode(', ', array_keys($this->availableTags)));
            return self::FAILURE;
        }

        if (! $tag) {
            $tag = $this->choice(
                'Which assets do you want to publish?',
                array_keys($this->availableTags),
                'all'
            );
        }

        $vendorTag = $this->availableTags[$tag];

        $this->info("Publishing: {$tag}...");
        Artisan::call('vendor:publish', [
            '--tag'   => $vendorTag,
            '--force' => $this->option('force'),
        ]);

        $this->line("Published successfully.");

        return self::SUCCESS;
    }
}
