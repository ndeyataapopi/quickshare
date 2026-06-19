<?php

namespace App\Modules\Repayments\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepaymentMade
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $loanId,
        public User $borrower,
        public float $amount,
    ) {
    }
}
