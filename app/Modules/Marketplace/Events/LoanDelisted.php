<?php

namespace App\Modules\Marketplace\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanDelisted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $loanId,
        public string $reason,
    ) {
    }
}
