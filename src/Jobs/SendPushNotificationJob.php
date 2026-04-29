<?php
namespace RahatulRabbi\LaravelChat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public array       $tokens,
        public ?int        $userId,
        public string      $title,
        public string      $body,
        public array       $data       = [],
        public ?string     $type       = null,
        public bool        $isDatabase = true
    ) {}

    public function handle(): void
    {
        if (empty($this->tokens)) {
            return;
        }

        if (! config('laravel-chat.push_notifications.enabled', false)) {
            return;
        }

        try {
            $messaging = app(\Kreait\Firebase\Contract\Messaging::class);

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($this->title, $this->body))
                ->withData(array_merge($this->data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']))
                ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray(['priority' => 'high']))
                ->withApnsConfig(\Kreait\Firebase\Messaging\ApnsConfig::fromArray([
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => ['sound' => 'default']],
                ]));

            $report = $messaging->sendMulticast($message, $this->tokens);

            foreach ($report->failures()->getItems() as $failure) {
                Log::warning('laravel-chat FCM failure: ' . $failure->error()->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('laravel-chat SendPushNotificationJob failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('laravel-chat SendPushNotificationJob permanently failed', [
            'user_id' => $this->userId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
