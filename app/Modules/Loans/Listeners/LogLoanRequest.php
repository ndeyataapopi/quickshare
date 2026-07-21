<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanRequested;

class LogLoanRequest
{
    public function handle(LoanRequested $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'action' => 'loan.requested',
            'description' => "Loan requested: R{$event->amount} for {$event->termMonths} days",
            'subject_type' => get_class($event->borrower),
            'subject_id' => $event->borrower->id,
            'metadata' => [
                'amount' => $event->amount,
                'term_days' => $event->termMonths,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
