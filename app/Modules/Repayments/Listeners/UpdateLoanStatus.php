<?php

namespace App\Modules\Repayments\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Models\Investment;
use App\Modules\Repayments\Events\LoanFullyRepaid;

class UpdateLoanStatus
{
    public function handle(LoanFullyRepaid $event): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'loan.fully_repaid',
            'description' => "Loan #{$event->loanId} has been fully repaid",
            'metadata' => ['loan_id' => $event->loanId],
            'ip_address' => request()->ip(),
        ]);

        Investment::where('loan_id', $event->loanId)
            ->where('status', 'active')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }
}
