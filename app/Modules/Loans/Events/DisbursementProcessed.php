<?php

namespace App\Modules\Loans\Events;

use App\Modules\Loans\Models\DisbursementTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisbursementProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DisbursementTransaction $transaction,
    ) {
    }
}
