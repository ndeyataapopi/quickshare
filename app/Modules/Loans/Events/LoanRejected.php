<?php

namespace App\Modules\Loans\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $loanId,
        public User $borrower,
        public string $reason,
    ) {
    }
}
