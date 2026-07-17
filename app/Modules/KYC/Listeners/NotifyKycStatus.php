<?php

namespace App\Modules\KYC\Listeners;

use App\Models\ActivityLog;
use App\Modules\KYC\Events\KycApproved;
use App\Modules\KYC\Events\KycRejected;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyKycStatus
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(KycApproved|KycRejected $event): void
    {
        try {
            $status = $event instanceof KycApproved ? 'approved' : 'rejected';

            Log::info('KYC status notification - Processing', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
                'status' => $status,
            ]);

            ActivityLog::create([
                'user_id' => $event->user->id,
                'action' => "kyc.{$status}",
                'description' => "KYC verification {$status}",
                'subject_type' => get_class($event->user),
                'subject_id' => $event->user->id,
                'metadata' => $event instanceof KycRejected ? ['reason' => $event->reason] : [],
                'ip_address' => request()->ip(),
            ]);

            // Send notification to user
            $notificationType = $event instanceof KycApproved ? 'kyc_approved' : 'kyc_rejected';
            $data = $event instanceof KycRejected ? ['reason' => $event->reason] : [];

            $result = $this->notificationService->send(
                $event->user,
                $notificationType,
                $data,
                ['email', 'database']
            );

            Log::info('KYC status notification - Sent successfully', [
                'user_id' => $event->user->id,
                'notification_type' => $notificationType,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send KYC status notification', [
                'user_id' => $event->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
