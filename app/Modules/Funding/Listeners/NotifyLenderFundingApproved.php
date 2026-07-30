<?php

namespace App\Modules\Funding\Listeners;

use App\Modules\Funding\Events\FundingPaymentApproved;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLenderFundingApproved implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(FundingPaymentApproved $event): void
    {
        $transaction = $event->transaction;
        $lender = $transaction->lender;

        if (! $lender) {
            return;
        }

        $this->notificationService->send(
            $lender,
            'funding_payment_approved',
            [
                'transaction_id' => $transaction->id,
                'reference' => $transaction->transaction_reference,
                'amount' => (float) $transaction->amount,
                'loan_reference' => $transaction->loan?->reference ?? 'N/A',
            ],
            ['email', 'database']
        );
    }
}
