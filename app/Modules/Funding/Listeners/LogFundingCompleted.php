<?php

namespace App\Modules\Funding\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Events\FundingCompleted;

class LogFundingCompleted
{
    public function handle(FundingCompleted $event): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'loan.funded',
            'description' => "Loan #{$event->loanId} fully funded with R{$event->totalFunded}",
            'loan_id' => $event->loanId,
            'amount' => $event->totalFunded,
            'previous_status' => 'partially_funded',
            'new_status' => 'funded',
            'metadata' => [
                'loan_id' => $event->loanId,
                'total_funded' => $event->totalFunded,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
