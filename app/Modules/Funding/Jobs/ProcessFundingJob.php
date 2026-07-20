<?php

namespace App\Modules\Funding\Jobs;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Services\FundingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFundingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60]; // seconds between retries

    public function __construct(
        public int $transactionId,
    ) {
    }

    public function handle(FundingService $fundingService): void
    {
        $transaction = FundingTransaction::find($this->transactionId);

        if (! $transaction) {
            Log::warning("ProcessFundingJob: Transaction {$this->transactionId} not found");
            return;
        }

        if (! $transaction->isPending()) {
            Log::info("ProcessFundingJob: Transaction {$this->transactionId} is not pending, skipping");
            return;
        }

        try {
            // Funding is now verified manually by an admin after the lender uploads
            // proof of payment. This job no longer auto-confirms transactions.

            Log::info("ProcessFundingJob: Transaction {$transaction->transaction_reference} is pending admin verification", [
                'transaction_id' => $transaction->id,
                'loan_id' => $transaction->loan_id,
                'lender_id' => $transaction->lender_id,
                'amount' => $transaction->amount,
            ]);
        } catch (\Throwable $e) {
            Log::error("ProcessFundingJob: Failed to process transaction {$this->transactionId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessFundingJob: Transaction {$this->transactionId} failed permanently", [
            'error' => $exception->getMessage(),
        ]);

        // Mark transaction as cancelled after all retries exhausted
        $transaction = FundingTransaction::find($this->transactionId);
        if ($transaction && $transaction->isPending()) {
            $transaction->update([
                'status' => 'cancelled',
                'notes' => 'Auto-cancelled due to processing failure: ' . $exception->getMessage(),
            ]);
        }
    }
}
