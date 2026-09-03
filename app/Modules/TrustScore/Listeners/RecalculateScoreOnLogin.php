<?php

namespace App\Modules\TrustScore\Listeners;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateScoreOnLogin implements ShouldQueue
{
    public string $queue = 'trust-score';

    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    public function handle(UserLoggedIn $event): void
    {
        $this->trustScoreService->recalculateForUser($event->user);
    }
}
