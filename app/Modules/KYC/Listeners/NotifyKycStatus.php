<?php

namespace App\Modules\KYC\Listeners;

use App\Models\ActivityLog;
use App\Modules\KYC\Events\KycApproved;
use App\Modules\KYC\Events\KycRejected;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyKycStatus implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(KycApproved|KycRejected $event): void
    {
        $status = $event instanceof KycApproved ? 'approved' : 'rejected';

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

        $this->notificationService->send(
            $event->user,
            $notificationType,
            $data,
            ['email', 'database']
        );
    }
}
