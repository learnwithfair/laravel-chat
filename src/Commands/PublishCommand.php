<?php
namespace RahatulRabbi\TalkBridge\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PublishCommand extends Command
{
    protected $signature   = 'talkbridge:publish {--tag= : config|migrations|stubs|lang|all} {--force}';
    protected $description = 'Publish specific TalkBridge assets';

    protected array $tags = [
        'config'     => 'talkbridge-config',
        'migrations' => 'talkbridge-migrations',
        'stubs'      => 'talkbridge-stubs',
        'lang'       => 'talkbridge-lang',
        'all'        => 'talkbridge',
    ];

    public function handle(): int
    {
        $tag = $this->option('tag') ?? $this->choice('Which assets?', array_keys($this->tags), 'all');

        if (! array_key_exists($tag, $this->tags)) {
            $this->error("Unknown tag: {$tag}");
            return self::FAILURE;
        }

        Artisan::call('vendor:publish', ['--tag' => $this->tags[$tag], '--force' => $this->option('force')]);
        $this->info("Published: {$tag}");
        return self::SUCCESS;
    }
}
