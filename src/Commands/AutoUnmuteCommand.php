<?php

namespace RahatulRabbi\TalkBridge\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RahatulRabbi\TalkBridge\Jobs\UnmuteConversationJob;

class AutoUnmuteCommand extends Command
{
    protected $signature = 'chat:auto-unmute
                            {--chunk=500 : Number of records to process per batch}';

    protected $description = 'Queue auto-unmute jobs for expired muted conversations';

    public function handle(): int
    {
        $chunkSize   = (int) $this->option('chunk');
        $totalQueued = 0;
        $connection  = config('laravel-chat.queue.connection');
        $queue       = config('laravel-chat.queue.name');

        DB::table('conversation_participants')
            ->where('is_muted', true)
            ->whereNotNull('muted_until')
            ->where('muted_until', '<=', Carbon::now())
            ->select('id', 'user_id', 'conversation_id')
            ->chunkById($chunkSize, function ($rows) use (&$totalQueued, $connection, $queue) {
                foreach ($rows as $row) {
                    UnmuteConversationJob::dispatch(
                        $row->id,
                        $row->user_id,
                        $row->conversation_id
                    )->onConnection($connection)->onQueue($queue);

                    $totalQueued++;
                }
            });

        if ($totalQueued > 0) {
            $this->line("Queued {$totalQueued} unmute job(s).");
        }

        return self::SUCCESS;
    }
}
