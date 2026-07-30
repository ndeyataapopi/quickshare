<?php

namespace App\Modules\Repayments\Listeners;

use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Repayments\Events\LenderRepaymentAllocated;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLenderRepaymentAllocated implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LenderRepaymentAllocated $event): void
    {
        $lr = $event->lenderRepayment;
        $lender = $lr->lender;

        if (! $lender) {
            return;
        }

        $this->notificationService->send(
            $lender,
            'lender_repayment_allocated',
            [
                'lender_repayment_id' => $lr->id,
                'amount' => (float) $lr->amount,
                'principal_return' => (float) $lr->principal_return,
                'interest_earned' => (float) $lr->interest_earned,
                'loan_reference' => $lr->repayment?->loan?->reference ?? 'N/A',
            ],
            ['email', 'database']
        );
    }
}
