<?php

namespace App\Modules\Repayments\Listeners;

use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\Loan;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Repayments\Events\LoanFullyRepaid;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyBorrowerLoanCompleted implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanFullyRepaid $event): void
    {
        $loan = Loan::with('borrower')->find($event->loanId);

        if (! $loan || ! $loan->borrower) {
            return;
        }

        $this->notificationService->send(
            $loan->borrower,
            'loan_completed',
            [
                'loan_id' => $event->loanId,
                'reference' => $loan->reference,
            ],
            ['email', 'database']
        );
    }
}
