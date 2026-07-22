<?php

namespace App\Modules\Funding\Events;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FundingPaymentApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FundingTransaction $transaction,
        public ?User $admin = null,
    ) {
    }
}
