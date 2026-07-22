<?php

namespace App\Modules\KYC\Listeners;

use App\Models\ActivityLog;
use App\Modules\KYC\Events\KycResubmitted;

class LogKycResubmission
{
    public function handle(KycResubmitted $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'actor_id' => $event->user->id,
            'action' => 'kyc.resubmitted',
            'description' => "KYC resubmitted by {$event->user->email}",
            'subject_type' => get_class($event->submission),
            'subject_id' => $event->submission->id,
            'previous_status' => 'resubmission_required',
            'new_status' => 'pending',
            'metadata' => [
                'submission_id' => $event->submission->id,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
