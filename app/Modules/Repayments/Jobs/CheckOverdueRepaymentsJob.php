<?php

namespace App\Modules\Repayments\Jobs;

use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckOverdueRepaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 600]; // 1min, 5min, 10min

    public function handle(RepaymentService $service): void
    {
        Log::info('CheckOverdueRepaymentsJob: Starting overdue check');

        try {
            $overdueCount = $service->checkOverdueRepayments();

            Log::info('CheckOverdueRepaymentsJob: Completed', [
                'overdue_found' => $overdueCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('CheckOverdueRepaymentsJob: Error checking overdue repayments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
