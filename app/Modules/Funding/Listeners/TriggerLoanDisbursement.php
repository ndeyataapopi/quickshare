<?php

namespace App\Modules\Funding\Listeners;

use App\Modules\Funding\Events\FundingCompleted;
use App\Modules\Loans\Events\LoanDisbursed;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerLoanDisbursement implements ShouldQueue
{
    public function handle(FundingCompleted $event): void
    {
        // Cross-module event dispatch: trigger loan disbursement
        LoanDisbursed::dispatch($event->loanId, $event->totalFunded);
    }
}
