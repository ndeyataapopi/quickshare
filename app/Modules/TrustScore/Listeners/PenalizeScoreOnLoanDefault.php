<?php

namespace App\Modules\TrustScore\Listeners;

use App\Modules\Loans\Events\LoanDefaulted;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;

class PenalizeScoreOnLoanDefault implements ShouldQueue
{
    public string $queue = 'trust-score';

    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    public function handle(LoanDefaulted $event): void
    {
        $borrower = $event->loan->borrower;

        if ($borrower) {
            $this->trustScoreService->onLoanDefault($borrower, $event->loan->id);
        }
    }
}
