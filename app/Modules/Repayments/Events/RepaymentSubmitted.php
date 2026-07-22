<?php

namespace App\Modules\Repayments\Events;

use App\Models\User;
use App\Modules\Loans\Models\DisbursementTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RepaymentSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Collection $repayments,
        public User $borrower,
        public float $totalAmount,
        public Collection $disbursements,
    ) {
    }
}
