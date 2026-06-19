<?php

namespace App\Modules\Collections\Jobs;

use App\Modules\Collections\Models\CollectionLog;
use App\Modules\Notifications\Events\NotificationSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 300]; // 30s, 1min, 5min

    public function __construct(
        public int $collectionLogId,
    ) {
    }

    public function handle(): void
    {
        $log = CollectionLog::find($this->collectionLogId);

        if (! $log) {
            Log::warning("SendNotificationJob: Collection log {$this->collectionLogId} not found");
            return;
        }

        if (! $log->isPending()) {
            Log::info("SendNotificationJob: Log {$this->collectionLogId} already processed");
            return;
        }

        try {
            // Simulate sending via different channels
            // In production, this would integrate with Twilio, SendGrid, etc.
            $externalRef = match ($log->channel) {
                'sms' => $this->sendSMS($log),
                'email' => $this->sendEmail($log),
                'whatsapp' => $this->sendWhatsApp($log),
                'voice' => $this->sendVoice($log),
                default => null,
            };

            $log->markAsSent($externalRef);
            $log->markAsDelivered(); // Simulate immediate delivery for now

            // Fire notification event
            NotificationSent::dispatch($log->borrower, $log->channel, 'collection_reminder');

            Log::info('Notification sent', [
                'collection_log_id' => $log->id,
                'channel' => $log->channel,
                'borrower_id' => $log->borrower_id,
                'external_reference' => $externalRef,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send notification', [
                'collection_log_id' => $log->id,
                'channel' => $log->channel,
                'error' => $e->getMessage(),
            ]);

            $log->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    protected function sendSMS(CollectionLog $log): string
    {
        // Integration with Twilio, AWS SNS, etc.
        // Simulate external reference
        return 'SMS-' . strtoupper(bin2hex(random_bytes(8)));
    }

    protected function sendEmail(CollectionLog $log): string
    {
        // Integration with SendGrid, Mailgun, etc.
        return 'EML-' . strtoupper(bin2hex(random_bytes(8)));
    }

    protected function sendWhatsApp(CollectionLog $log): string
    {
        // Integration with Twilio WhatsApp, etc.
        return 'WAP-' . strtoupper(bin2hex(random_bytes(8)));
    }

    protected function sendVoice(CollectionLog $log): string
    {
        // Integration with Twilio Voice, etc.
        return 'VCE-' . strtoupper(bin2hex(random_bytes(8)));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotificationJob: Permanent failure for log {$this->collectionLogId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
