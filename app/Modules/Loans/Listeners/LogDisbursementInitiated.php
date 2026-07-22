<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\DisbursementInitiated;

class LogDisbursementInitiated
{
    public function handle(DisbursementInitiated $event): void
    {
        $t = $event->transaction;

        ActivityLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'action' => 'disbursement.initiated',
            'description' => "Disbursement initiated for loan #{$t->loan_id}: R{$t->net_amount}",
            'subject_type' => get_class($t),
            'subject_id' => $t->id,
            'loan_id' => $t->loan_id,
            'disbursement_transaction_id' => $t->id,
            'amount' => (float) $t->net_amount,
            'previous_status' => null,
            'new_status' => $t->status,
            'metadata' => [
                'gross_amount' => (float) $t->gross_amount,
                'platform_fee' => (float) $t->platform_fee,
                'reference' => $t->transaction_reference,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
