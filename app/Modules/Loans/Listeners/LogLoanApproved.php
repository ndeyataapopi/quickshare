<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanApproved;

class LogLoanApproved
{
    public function handle(LoanApproved $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'actor_id' => auth()->id(),
            'action' => 'loan.approved',
            'description' => "Loan #{$event->loanId} approved for {$event->borrower->first_name} {$event->borrower->last_name}",
            'subject_type' => \App\Modules\Loans\Models\Loan::class,
            'subject_id' => $event->loanId,
            'loan_id' => $event->loanId,
            'previous_status' => 'pending_review',
            'new_status' => 'marketplace',
            'metadata' => [
                'borrower_id' => $event->borrower->id,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
