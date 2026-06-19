<?php

namespace App\Modules\Loans\Jobs;

use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Services\DisbursementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDisbursementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // We handle retries via service logic
    public array $backoff = [60]; // 1 minute for queue-level retry

    public function __construct(
        public int $disbursementId,
    ) {
    }

    public function handle(DisbursementService $service): void
    {
        $transaction = DisbursementTransaction::find($this->disbursementId);

        if (! $transaction) {
            Log::warning("ProcessDisbursementJob: Transaction {$this->disbursementId} not found");
            return;
        }

        if (! $transaction->isAwaiting() && ! $transaction->isFailed()) {
            Log::info("ProcessDisbursementJob: Transaction {$this->disbursementId} status is {$transaction->status}, skipping");
            return;
        }

        try {
            $service->processDisbursement($transaction);

            Log::info("ProcessDisbursementJob: Transaction {$this->disbursementId} processed successfully");
        } catch (\Throwable $e) {
            Log::error("ProcessDisbursementJob: Failed to process transaction {$this->disbursementId}", [
                'error' => $e->getMessage(),
            ]);

            // Don't re-throw - service handles retry logic internally
        }
    }
}
