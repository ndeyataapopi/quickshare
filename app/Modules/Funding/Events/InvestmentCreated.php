<?php

namespace App\Modules\Funding\Events;

use App\Modules\Funding\Models\Investment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvestmentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Investment $investment,
    ) {
    }
}
