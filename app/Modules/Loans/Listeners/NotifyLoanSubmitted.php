<?php

namespace App\Modules\Loans\Listeners;

use App\Modules\Loans\Events\LoanRequested;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyLoanSubmitted implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanRequested $event): void
    {
        try {
            $this->notificationService->send(
                $event->borrower,
                'loan_submitted',
                [
                    'loan_id' => $event->loanId,
                    'reference' => $event->loanId ? optional(\App\Modules\Loans\Models\Loan::find($event->loanId))->reference : null,
                    'amount' => $event->amount,
                    'term_days' => $event->termMonths,
                ],
                ['email', 'database']
            );
        } catch (\Throwable $e) {
            Log::error('NotifyLoanSubmitted: Failed to send notification', [
                'user_id' => $event->borrower->id,
                'loan_id' => $event->loanId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
