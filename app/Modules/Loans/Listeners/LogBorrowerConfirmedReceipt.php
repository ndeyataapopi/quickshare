<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\BorrowerConfirmedReceipt;

class LogBorrowerConfirmedReceipt
{
    public function handle(BorrowerConfirmedReceipt $event): void
    {
        $t = $event->transaction;

        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'actor_id' => $event->borrower->id,
            'action' => 'disbursement.borrower_confirmed',
            'description' => "Borrower confirmed receipt of disbursement for loan #{$t->loan_id}",
            'subject_type' => get_class($t),
            'subject_id' => $t->id,
            'loan_id' => $t->loan_id,
            'disbursement_transaction_id' => $t->id,
            'amount' => (float) $t->net_amount,
            'previous_status' => 'pending_borrower_confirmation',
            'new_status' => 'disbursed',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
