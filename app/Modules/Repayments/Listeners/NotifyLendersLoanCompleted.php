<?php

namespace App\Modules\Repayments\Listeners;

use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\Loan;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Repayments\Events\LoanFullyRepaid;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLendersLoanCompleted implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanFullyRepaid $event): void
    {
        $investments = Investment::with(['lender', 'loan'])
            ->where('loan_id', $event->loanId)
            ->whereIn('status', ['active', 'completed'])
            ->get();

        foreach ($investments as $investment) {
            if (! $investment->lender) {
                continue;
            }

            $this->notificationService->send(
                $investment->lender,
                'loan_completed',
                [
                    'loan_id' => $event->loanId,
                    'reference' => $investment->loan?->reference ?? 'N/A',
                    'investment_id' => $investment->id,
                    'invested_amount' => (float) $investment->amount,
                    'actual_return' => (float) $investment->actual_return,
                ],
                ['email', 'database']
            );
        }
    }
}
