<?php

namespace App\Modules\Repayments\Events;

use App\Models\User;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepaymentRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Repayment $repayment,
        public ?User $admin = null,
        public ?string $reason = null,
    ) {
    }
}
