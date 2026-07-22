<?php

namespace App\Modules\Repayments\Listeners;

use App\Models\ActivityLog;
use App\Modules\Repayments\Events\LenderRepaymentAllocated;

class LogLenderRepaymentAllocated
{
    public function handle(LenderRepaymentAllocated $event): void
    {
        $lr = $event->lenderRepayment;

        ActivityLog::create([
            'user_id' => $lr->lender_id,
            'actor_id' => auth()->id(),
            'action' => 'lender_repayment.allocated',
            'description' => "Lender repayment allocated: R{$lr->amount} to lender #{$lr->lender_id}",
            'subject_type' => get_class($lr),
            'subject_id' => $lr->id,
            'loan_id' => $lr->repayment->loan_id,
            'repayment_id' => $lr->repayment_id,
            'funding_transaction_id' => $lr->funding_transaction_id,
            'amount' => (float) $lr->amount,
            'metadata' => [
                'lender_id' => $lr->lender_id,
                'principal_return' => (float) $lr->principal_return,
                'interest_earned' => (float) $lr->interest_earned,
                'funding_percentage' => (float) $lr->funding_percentage,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
