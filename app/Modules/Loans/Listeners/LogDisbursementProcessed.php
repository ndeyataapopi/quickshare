<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\DisbursementProcessed;

class LogDisbursementProcessed
{
    public function handle(DisbursementProcessed $event): void
    {
        $t = $event->transaction;

        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'action' => 'disbursement.processed',
            'description' => "Disbursement processed for loan #{$t->loan_id}: R{$t->net_amount}",
            'subject_type' => get_class($t),
            'subject_id' => $t->id,
            'loan_id' => $t->loan_id,
            'disbursement_transaction_id' => $t->id,
            'amount' => (float) $t->net_amount,
            'previous_status' => 'awaiting_disbursement',
            'new_status' => $t->status,
            'metadata' => [
                'payment_method' => $t->payment_method,
                'external_reference' => $t->external_reference,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
