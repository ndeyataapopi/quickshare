<?php

namespace App\Modules\TrustScore\Listeners;

use App\Modules\KYC\Events\KycApproved;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;

class BoostScoreOnKycApproval implements ShouldQueue
{
    public string $queue = 'trust-score';

    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    public function handle(KycApproved $event): void
    {
        $this->trustScoreService->onKycApproved($event->user);
    }
}
