<?php

namespace App\Modules\TrustScore\Listeners;

use App\Models\User;
use App\Modules\TrustScore\Services\TrustScoreService;

class AdjustScoreOnReferralOutcome
{
    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    public function handleCompleted(User $referrer, int $referredUserId): void
    {
        $this->trustScoreService->onReferralCompleted($referrer, $referredUserId);
    }

    public function handleDefaulted(User $referrer, int $referredUserId): void
    {
        $this->trustScoreService->onReferralDefaulted($referrer, $referredUserId);
    }
}
