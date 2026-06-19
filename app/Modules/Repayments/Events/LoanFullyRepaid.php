<?php

namespace App\Modules\Repayments\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanFullyRepaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $loanId)
    {
    }
}
