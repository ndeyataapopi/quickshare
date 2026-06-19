<?php

namespace App\Modules\KYC\Listeners;

use App\Modules\KYC\Events\KycApproved;

class UpdateUserVerificationStatus
{
    public function handle(KycApproved $event): void
    {
        $event->user->update(['email_verified_at' => now()]);
    }
}
