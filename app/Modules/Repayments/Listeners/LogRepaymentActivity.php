<?php

namespace App\Modules\Repayments\Listeners;

use App\Models\ActivityLog;
use App\Modules\Repayments\Events\RepaymentMade;

class LogRepaymentActivity
{
    public function handle(RepaymentMade $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'action' => 'repayment.made',
            'description' => "Repayment of R{$event->amount} on loan #{$event->loanId}",
            'subject_type' => get_class($event->borrower),
            'subject_id' => $event->borrower->id,
            'metadata' => [
                'loan_id' => $event->loanId,
                'amount' => $event->amount,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
