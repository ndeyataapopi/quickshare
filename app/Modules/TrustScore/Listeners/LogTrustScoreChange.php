<?php

namespace App\Modules\TrustScore\Listeners;

use App\Models\ActivityLog;
use App\Modules\TrustScore\Events\TrustScoreCalculated;
use App\Modules\TrustScore\Services\TrustScoreService;

class LogTrustScoreChange
{
    public function handle(TrustScoreCalculated $event): void
    {
        $change = round($event->newScore - $event->previousScore, 2);
        $direction = $change >= 0 ? 'increased' : 'decreased';
        $previousTier = TrustScoreService::getTier($event->previousScore);
        $newTier = TrustScoreService::getTier($event->newScore);

        $description = "Trust score {$direction} from {$event->previousScore} to {$event->newScore}";
        if ($previousTier !== $newTier) {
            $description .= " (tier changed: {$previousTier} → {$newTier})";
        }

        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'trust_score.calculated',
            'description' => $description,
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'metadata' => [
                'previous_score' => $event->previousScore,
                'new_score' => $event->newScore,
                'change' => $change,
                'previous_tier' => $previousTier,
                'new_tier' => $newTier,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
