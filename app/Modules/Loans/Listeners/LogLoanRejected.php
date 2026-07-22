<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanRejected;

class LogLoanRejected
{
    public function handle(LoanRejected $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'actor_id' => auth()->id(),
            'action' => 'loan.rejected',
            'description' => "Loan #{$event->loanId} rejected: {$event->reason}",
            'subject_type' => \App\Modules\Loans\Models\Loan::class,
            'subject_id' => $event->loanId,
            'loan_id' => $event->loanId,
            'previous_status' => 'pending_review',
            'new_status' => 'cancelled',
            'metadata' => [
                'borrower_id' => $event->borrower->id,
                'reason' => $event->reason,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
