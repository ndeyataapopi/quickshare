<?php

namespace App\Modules\Funding\Events;

use App\Modules\Funding\Models\FundingTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FundingPaymentSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FundingTransaction $transaction,
    ) {
    }
}
