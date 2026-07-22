<?php

namespace App\Modules\KYC\Events;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycResubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public KycSubmission $submission,
    ) {
    }
}
