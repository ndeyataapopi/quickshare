<?php

namespace App\Modules\Funding\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Events\FundingInitiated;

class LogFundingInitiated
{
    public function handle(FundingInitiated $event): void
    {
        ActivityLog::create([
            'user_id' => $event->lender->id,
            'actor_id' => $event->lender->id,
            'action' => 'funding.initiated',
            'description' => "Funding initiated: R{$event->transaction->amount} on loan #{$event->transaction->loan_id}",
            'subject_type' => get_class($event->transaction),
            'subject_id' => $event->transaction->id,
            'loan_id' => $event->transaction->loan_id,
            'funding_transaction_id' => $event->transaction->id,
            'amount' => (float) $event->transaction->amount,
            'metadata' => [
                'lender_id' => $event->lender->id,
                'transaction_reference' => $event->transaction->transaction_reference,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
