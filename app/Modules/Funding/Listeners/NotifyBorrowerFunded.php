<?php

namespace App\Modules\Funding\Listeners;

use App\Modules\Funding\Events\LoanFunded;
use App\Modules\Loans\Models\Loan;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyBorrowerFunded implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanFunded $event): void
    {
        // Load loan from ID
        $loan = Loan::find($event->loanId);

        if (! $loan) {
            Log::error("NotifyBorrowerFunded: Loan {$event->loanId} not found");
            return;
        }

        // Notify borrower that their loan is fully funded
        $this->notificationService->send(
            $loan->borrower,
            'loan_funded',
            [
                'loan_id' => $loan->id,
                'reference' => $loan->reference,
                'amount' => $loan->approved_amount,
            ],
            ['email', 'database', 'sms']
        );
    }
}
