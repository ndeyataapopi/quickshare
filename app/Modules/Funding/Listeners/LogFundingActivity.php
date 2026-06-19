<?php

namespace App\Modules\Funding\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Events\LoanFunded;

class LogFundingActivity
{
    public function handle(LoanFunded $event): void
    {
        ActivityLog::create([
            'user_id' => $event->lender->id,
            'action' => 'funding.loan_funded',
            'description' => "Lender funded R{$event->amount} on loan #{$event->loanId}",
            'subject_type' => get_class($event->lender),
            'subject_id' => $event->lender->id,
            'metadata' => [
                'loan_id' => $event->loanId,
                'amount' => $event->amount,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
