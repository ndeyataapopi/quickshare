<?php

namespace App\Modules\TrustScore\Listeners;

use App\Modules\Repayments\Events\LoanFullyRepaid;
use App\Modules\Repayments\Events\RepaymentMade;
use App\Modules\Repayments\Events\RepaymentOverdue;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateTrustScore implements ShouldQueue
{
    public string $queue = 'trust-score';

    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    public function handle(RepaymentMade|RepaymentOverdue|LoanFullyRepaid $event): void
    {
        match (true) {
            $event instanceof RepaymentMade => $this->trustScoreService->onRepaymentMade(
                $event->borrower,
                $event->amount,
                $event->loanId,
            ),
            $event instanceof RepaymentOverdue => $this->trustScoreService->onRepaymentOverdue(
                $event->borrower,
                $event->daysOverdue,
                $event->loanId,
            ),
            $event instanceof LoanFullyRepaid => null, // handled by dedicated listener
        };
    }
}
