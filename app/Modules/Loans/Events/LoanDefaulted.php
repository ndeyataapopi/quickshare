<?php

namespace App\Modules\Loans\Events;

use App\Modules\Loans\Models\Loan;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanDefaulted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Loan $loan,
        public ?int $repaymentId = null,
    ) {
    }
}
