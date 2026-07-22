<?php

namespace App\Modules\Funding\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Events\FundingPaymentSubmitted;

class LogFundingPaymentSubmitted
{
    public function handle(FundingPaymentSubmitted $event): void
    {
        $t = $event->transaction;

        ActivityLog::create([
            'user_id' => $t->lender_id,
            'actor_id' => $t->lender_id,
            'action' => 'funding.payment_submitted',
            'description' => "Funding payment submitted for R{$t->amount} on loan #{$t->loan_id}",
            'subject_type' => get_class($t),
            'subject_id' => $t->id,
            'loan_id' => $t->loan_id,
            'funding_transaction_id' => $t->id,
            'amount' => (float) $t->amount,
            'metadata' => [
                'payment_method' => $t->payment_method,
                'payment_reference' => $t->payment_reference,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
