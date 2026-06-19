<?php

namespace App\Modules\Repayments\Listeners;

use App\Modules\Loans\Models\Loan;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Repayments\Events\RepaymentMade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyRepaymentReceived implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(RepaymentMade $event): void
    {
        $loan = Loan::with('borrower')->find($event->loanId);

        if (! $loan) {
            Log::error("NotifyRepaymentReceived: Loan {$event->loanId} not found");
            return;
        }

        // Notify borrower of received repayment
        $this->notificationService->send(
            $event->borrower,
            'repayment_received',
            [
                'loan_id' => $loan->id,
                'reference' => $loan->reference,
                'amount' => $event->amount,
                'remaining_balance' => $loan->outstanding_balance ?? 0,
            ],
            ['email', 'database']
        );
    }
}
