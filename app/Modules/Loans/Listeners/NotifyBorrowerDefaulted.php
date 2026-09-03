<?php

namespace App\Modules\Loans\Listeners;

use App\Modules\Loans\Events\LoanDefaulted;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyBorrowerDefaulted implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanDefaulted $event): void
    {
        $loan = $event->loan;
        $borrower = $loan->borrower;

        if (! $borrower) {
            return;
        }

        $this->notificationService->send(
            $borrower,
            'loan_defaulted',
            [
                'loan_id' => $loan->id,
                'reference' => $loan->reference,
                'amount' => $loan->outstanding_balance ?? $loan->total_repayment,
            ],
            ['email', 'database', 'sms']
        );
    }
}
