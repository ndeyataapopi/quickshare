<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Console\Command;

class RecalculateTrustScores extends Command
{
    protected $signature = 'trust-score:recalculate {--user= : Recalculate for a specific user ID}';

    protected $description = 'Recalculate trust scores for all users (or a specific user) based on their actual account history.';

    public function handle(TrustScoreService $service): int
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::find($userId);

            if (! $user) {
                $this->error("User ID {$userId} not found.");

                return self::FAILURE;
            }

            $oldScore = (float) $user->trust_score;
            $newScore = $service->recalculateForUser($user);

            $this->info("User #{$user->id} ({$user->full_name}): {$oldScore} -> {$newScore}");

            return self::SUCCESS;
        }

        $users = User::orderBy('id')->get();
        $updated = 0;
        $unchanged = 0;

        $this->info("Recalculating trust scores for {$users->count()} users...");

        foreach ($users as $user) {
            $oldScore = (float) $user->trust_score;
            $newScore = $service->recalculateForUser($user);

            if (abs($oldScore - $newScore) >= 0.01) {
                $updated++;
                $this->line("  User #{$user->id} ({$user->full_name}): {$oldScore} -> {$newScore}");
            } else {
                $unchanged++;
            }
        }

        $this->newLine();
        $this->info("Done. {$updated} scores updated, {$unchanged} unchanged.");

        return self::SUCCESS;
    }
}
