<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanCancelled;

class LogLoanCancelled
{
    public function handle(LoanCancelled $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'actor_id' => $event->borrower->id,
            'action' => 'loan.cancelled',
            'description' => "Loan #{$event->loan->id} cancelled by borrower",
            'subject_type' => get_class($event->loan),
            'subject_id' => $event->loan->id,
            'loan_id' => $event->loan->id,
            'previous_status' => $event->loan->getOriginal('status'),
            'new_status' => 'cancelled',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
