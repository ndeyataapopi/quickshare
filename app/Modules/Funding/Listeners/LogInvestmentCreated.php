<?php

namespace App\Modules\Funding\Listeners;

use App\Models\ActivityLog;
use App\Modules\Funding\Events\InvestmentCreated;

class LogInvestmentCreated
{
    public function handle(InvestmentCreated $event): void
    {
        $inv = $event->investment;

        ActivityLog::create([
            'user_id' => $inv->lender_id,
            'actor_id' => $inv->lender_id,
            'action' => 'investment.created',
            'description' => "Investment created: R{$inv->amount} on loan #{$inv->loan_id}",
            'subject_type' => get_class($inv),
            'subject_id' => $inv->id,
            'loan_id' => $inv->loan_id,
            'investment_id' => $inv->id,
            'funding_transaction_id' => $inv->funding_transaction_id,
            'amount' => (float) $inv->amount,
            'metadata' => [
                'interest_rate' => (float) $inv->interest_rate,
                'expected_return' => (float) $inv->expected_return,
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
