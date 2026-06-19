<?php

namespace App\Modules\KYC\Listeners;

use App\Models\ActivityLog;
use App\Modules\KYC\Events\KycSubmitted;

class LogKycSubmission
{
    public function handle(KycSubmitted $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'kyc.submitted',
            'description' => "KYC documents submitted: {$event->documentType}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'metadata' => ['document_type' => $event->documentType],
            'ip_address' => request()->ip(),
        ]);
    }
}
