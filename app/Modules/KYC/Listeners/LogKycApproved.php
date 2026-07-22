<?php

namespace App\Modules\KYC\Listeners;

use App\Models\ActivityLog;
use App\Modules\KYC\Events\KycApproved;

class LogKycApproved
{
    public function handle(KycApproved $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'actor_id' => auth()->id(),
            'action' => 'kyc.approved',
            'description' => "KYC approved for {$event->user->email}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
            'metadata' => [
                'reviewer_id' => auth()->id(),
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
