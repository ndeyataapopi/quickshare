<?php

namespace App\Modules\Loans\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanDisbursed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $loanId,
        public float $amount,
    ) {
    }
}
