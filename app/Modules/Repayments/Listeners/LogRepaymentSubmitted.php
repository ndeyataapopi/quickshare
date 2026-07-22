<?php

namespace App\Modules\Repayments\Listeners;

use App\Models\ActivityLog;
use App\Modules\Repayments\Events\RepaymentSubmitted;

class LogRepaymentSubmitted
{
    public function handle(RepaymentSubmitted $event): void
    {
        foreach ($event->disbursements as $disbursement) {
            ActivityLog::create([
                'user_id' => $event->borrower->id,
                'actor_id' => $event->borrower->id,
                'action' => 'repayment.submitted',
                'description' => "Repayment submitted: R{$event->totalAmount} for loan #{$disbursement->loan_id}",
                'subject_type' => get_class($disbursement),
                'subject_id' => $disbursement->id,
                'loan_id' => $disbursement->loan_id,
                'disbursement_transaction_id' => $disbursement->id,
                'amount' => $event->totalAmount,
                'previous_status' => 'pending',
                'new_status' => 'pending_approval',
                'metadata' => [
                    'borrower_id' => $event->borrower->id,
                    'repayment_ids' => $event->repayments->pluck('id')->toArray(),
                    'payment_method' => $disbursement->payment_method,
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        }
    }
}
