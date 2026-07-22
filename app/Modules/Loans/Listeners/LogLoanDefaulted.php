<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanDefaulted;

class LogLoanDefaulted
{
    public function handle(LoanDefaulted $event): void
    {
        ActivityLog::create([
            'user_id' => $event->loan->borrower_id,
            'actor_id' => auth()->id(),
            'action' => 'loan.defaulted',
            'description' => "Loan #{$event->loan->id} marked as defaulted",
            'subject_type' => get_class($event->loan),
            'subject_id' => $event->loan->id,
            'loan_id' => $event->loan->id,
            'repayment_id' => $event->repaymentId,
            'previous_status' => $event->loan->getOriginal('status'),
            'new_status' => 'defaulted',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
