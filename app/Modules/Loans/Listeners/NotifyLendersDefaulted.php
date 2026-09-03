<?php

namespace App\Modules\Loans\Listeners;

use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Events\LoanDefaulted;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLendersDefaulted implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanDefaulted $event): void
    {
        $loan = $event->loan;

        $investments = Investment::with('lender')
            ->where('loan_id', $loan->id)
            ->where('status', 'active')
            ->get();

        foreach ($investments as $investment) {
            if (! $investment->lender) {
                continue;
            }

            $this->notificationService->send(
                $investment->lender,
                'loan_defaulted',
                [
                    'loan_id' => $loan->id,
                    'reference' => $loan->reference,
                    'investment_id' => $investment->id,
                    'invested_amount' => (float) $investment->amount,
                ],
                ['email', 'database']
            );
        }
    }
}
