<?php

namespace App\Modules\Repayments\Listeners;

use App\Models\ActivityLog;
use App\Modules\Repayments\Events\RepaymentRejected;

class LogRepaymentRejected
{
    public function handle(RepaymentRejected $event): void
    {
        $r = $event->repayment;

        ActivityLog::create([
            'user_id' => $r->borrower_id,
            'actor_id' => $event->admin?->id,
            'action' => 'repayment.rejected',
            'description' => "Repayment #{$r->id} rejected for loan #{$r->loan_id}",
            'subject_type' => get_class($r),
            'subject_id' => $r->id,
            'loan_id' => $r->loan_id,
            'repayment_id' => $r->id,
            'amount' => (float) $r->amount,
            'previous_status' => 'pending_approval',
            'new_status' => 'rejected',
            'metadata' => [
                'admin_id' => $event->admin?->id,
                'reason' => $event->reason,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
