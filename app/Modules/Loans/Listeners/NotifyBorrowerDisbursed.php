<?php

namespace App\Modules\Loans\Listeners;

use App\Modules\Loans\Events\LoanDisbursed;
use App\Modules\Loans\Models\Loan;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyBorrowerDisbursed implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanDisbursed $event): void
    {
        $loan = Loan::with('borrower')->find($event->loanId);

        if (! $loan || ! $loan->borrower) {
            return;
        }

        $this->notificationService->send(
            $loan->borrower,
            'loan_disbursed',
            [
                'loan_id' => $event->loanId,
                'reference' => $loan->reference,
                'amount' => $event->amount,
            ],
            ['email', 'database']
        );
    }
}
