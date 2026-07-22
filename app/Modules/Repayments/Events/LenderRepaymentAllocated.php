<?php

namespace App\Modules\Repayments\Events;

use App\Modules\Repayments\Models\LenderRepayment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LenderRepaymentAllocated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LenderRepayment $lenderRepayment,
    ) {
    }
}
