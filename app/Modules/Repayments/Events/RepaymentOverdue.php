<?php

namespace App\Modules\Repayments\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepaymentOverdue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $loanId,
        public User $borrower,
        public int $daysOverdue,
    ) {
    }
}
