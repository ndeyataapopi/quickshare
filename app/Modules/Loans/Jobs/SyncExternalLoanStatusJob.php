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

class SyncExternalLoanStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public $backoff = [60, 300, 600];

    public function __construct(
        public int $loanId,
    ) {
    }

    public function handle(MifosAdapter $adapter): void
    {
        $loan = Loan::find($this->loanId);

        if (! $loan || ! $loan->external_loan_id) {
            Log::warning("SyncExternalLoanStatusJob: Loan {$this->loanId} missing or no external_loan_id.");
            return;
        }

        $log = ReconciliationLog::create([
            'loan_id' => $loan->id,
            'external_loan_id' => $loan->external_loan_id,
            'provider' => $adapter->getProviderName(),
            'operation' => 'status_sync',
            'direction' => 'inbound',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        try {
            $result = $adapter->getLoanStatus($loan->external_loan_id);

            $log->update([
                'status' => $result['success'] ? 'success' : 'failed',
                'response_payload' => $result,
                'http_status' => $result['status'] ?? null,
                'error_message' => $result['error'] ?? null,
                'completed_at' => now(),
            ]);

            if ($result['success'] ?? false) {
                $externalStatus = data_get($result, 'data.status.value') ?? data_get($result, 'data.status');

                // Map Mifos statuses to QuickShare statuses
                $mappedStatus = $externalStatus ? $this->mapMifosStatusToQuickShare($externalStatus) : null;

                if ($mappedStatus && $mappedStatus !== $loan->status) {
                    $loan->update([
                        'status' => $mappedStatus,
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]);

                    Log::info("Loan {$loan->id} status synced from {$externalStatus} to {$mappedStatus}");
                } else {
                    $loan->update([
                        'sync_status' => 'synced',
                        'last_synced_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    protected function mapMifosStatusToQuickShare(string $mifosStatus): ?string
    {
        return match (strtolower($mifosStatus)) {
            'submitted', 'submitted and pending approval' => 'pending_review',
            'approved', 'pending disbursement' => 'funded',
            'active', 'disbursed', 'in arrears' => 'active',
            'overpaid', 'closed', 'withdrawn by client', 'written off' => 'completed',
            'rejected' => 'cancelled',
            default => null,
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncExternalLoanStatusJob failed", [
            'loan_id' => $this->loanId,
            'error' => $exception->getMessage(),
        ]);
    }
}
