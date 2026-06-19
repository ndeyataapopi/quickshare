<?php

namespace App\Modules\Loans\Jobs;

use App\Modules\Loans\Adapters\MifosAdapter;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Models\ReconciliationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLoanToExternalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public $backoff = [60, 300, 600]; // 1min, 5min, 10min

    public function __construct(
        public int $loanId,
        public string $operation = 'create', // create | update | approve | reject | disburse
    ) {
    }

    public function handle(MifosAdapter $adapter): void
    {
        $loan = Loan::find($this->loanId);

        if (! $loan) {
            Log::warning("SyncLoanToExternalJob: Loan {$this->loanId} not found.");
            return;
        }

        // Don't sync loans in very early states
        if (! in_array($loan->status, [
            'pending_review', 'marketplace', 'partially_funded',
            'funded', 'awaiting_disbursement', 'disbursed',
            'active', 'completed', 'overdue', 'defaulted',
        ])) {
            Log::info("SyncLoanToExternalJob: Skipping loan {$loan->id} with status {$loan->status}.");
            return;
        }

        $log = ReconciliationLog::create([
            'loan_id' => $loan->id,
            'external_loan_id' => $loan->external_loan_id,
            'provider' => $adapter->getProviderName(),
            'operation' => $this->operation,
            'direction' => 'outbound',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        try {
            $result = match ($this->operation) {
                'create' => $adapter->createLoan($loan),
                'update' => $adapter->updateLoan($loan),
                'approve' => $adapter->approveLoan($loan),
                'reject' => $adapter->rejectLoan($loan, $loan->rejection_reason ?? 'Rejected'),
                'disburse' => $adapter->disburseLoan($loan),
                default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}"),
            };

            $log->update([
                'status' => $result['success'] ? 'success' : 'failed',
                'response_payload' => $result,
                'http_status' => $result['status'] ?? null,
                'error_message' => $result['error'] ?? null,
                'completed_at' => now(),
            ]);

            // On successful create, store the external loan ID
            if ($this->operation === 'create' && ($result['success'] ?? false)) {
                $externalId = data_get($result, 'data.loanId') ?? data_get($result, 'data.resourceId');
                if ($externalId) {
                    $loan->update([
                        'external_loan_id' => $externalId,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                        'external_provider' => $adapter->getProviderName(),
                    ]);
                }
            }

            if ($this->operation !== 'create' && ($result['success'] ?? false)) {
                $loan->update([
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e; // Re-throw so Laravel retries
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncLoanToExternalJob failed permanently", [
            'loan_id' => $this->loanId,
            'operation' => $this->operation,
            'error' => $exception->getMessage(),
        ]);
    }
}
