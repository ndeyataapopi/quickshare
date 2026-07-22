<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanActivated;

class LogLoanActivated
{
    public function handle(LoanActivated $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'actor_id' => $event->borrower->id,
            'action' => 'loan.activated',
            'description' => "Loan #{$event->loan->id} activated",
            'subject_type' => get_class($event->loan),
            'subject_id' => $event->loan->id,
            'loan_id' => $event->loan->id,
            'previous_status' => 'awaiting_disbursement',
            'new_status' => 'active',
            'metadata' => [
                'borrower_id' => $event->borrower->id,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
