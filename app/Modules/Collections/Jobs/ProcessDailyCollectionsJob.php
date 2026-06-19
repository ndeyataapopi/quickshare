<?php

namespace App\Modules\Collections\Jobs;

use App\Modules\Collections\Services\CollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDailyCollectionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(CollectionService $service): void
    {
        Log::info('ProcessDailyCollectionsJob: Starting daily collections run');

        try {
            // Process reminders
            $reminderStats = $service->processDailyReminders();
            Log::info('ProcessDailyCollectionsJob: Reminders processed', $reminderStats);

            // Process escalations
            $escalationStats = $service->processEscalations();
            Log::info('ProcessDailyCollectionsJob: Escalations processed', $escalationStats);

            Log::info('ProcessDailyCollectionsJob: Daily collections run completed', [
                'reminders_sent' => $reminderStats['sent'],
                'reminders_failed' => $reminderStats['failed'],
                'escalations_processed' => array_sum($escalationStats),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessDailyCollectionsJob: Error processing daily collections', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
