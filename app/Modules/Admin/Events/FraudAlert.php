<?php

namespace App\Modules\Admin\Events;

use App\Models\User;
use App\Modules\Admin\Models\FraudFlag;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FraudAlert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FraudFlag $fraudFlag,
        public User $subject,
        public string $alertType, // 'new_flag', 'severity_escalation', 'confirmed_fraud'
    ) {
    }
}
