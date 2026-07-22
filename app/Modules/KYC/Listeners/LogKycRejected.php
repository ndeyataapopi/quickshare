<?php

namespace App\Modules\KYC\Listeners;

use App\Models\ActivityLog;
use App\Modules\KYC\Events\KycRejected;

class LogKycRejected
{
    public function handle(KycRejected $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'actor_id' => auth()->id(),
            'action' => 'kyc.rejected',
            'description' => "KYC rejected for {$event->user->email}: {$event->reason}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'previous_status' => 'pending',
            'new_status' => 'rejected',
            'metadata' => [
                'reviewer_id' => auth()->id(),
                'reason' => $event->reason,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
