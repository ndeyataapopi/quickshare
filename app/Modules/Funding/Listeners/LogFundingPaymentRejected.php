<?php

namespace App\Modules\Funding\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Events\FundingPaymentRejected;

class LogFundingPaymentRejected
{
    public function handle(FundingPaymentRejected $event): void
    {
        $t = $event->transaction;

        ActivityLog::create([
            'user_id' => $t->lender_id,
            'actor_id' => $event->admin?->id,
            'action' => 'funding.payment_rejected',
            'description' => "Funding payment rejected: R{$t->amount} on loan #{$t->loan_id}",
            'subject_type' => get_class($t),
            'subject_id' => $t->id,
            'loan_id' => $t->loan_id,
            'funding_transaction_id' => $t->id,
            'amount' => (float) $t->amount,
            'previous_status' => 'pending',
            'new_status' => 'rejected',
            'metadata' => [
                'admin_id' => $event->admin?->id,
                'reason' => $event->reason,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
