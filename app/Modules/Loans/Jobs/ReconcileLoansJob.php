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

class ReconcileLoansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $provider = null,
    ) {
    }

    public function handle(MifosAdapter $adapter): void
    {
        if (! $adapter->isHealthy()) {
            Log::warning("ReconcileLoansJob: Provider not healthy, skipping.");
            return;
        }

        $provider = $this->provider ?? $adapter->getProviderName();

        $log = ReconciliationLog::create([
            'loan_id' => null, // System-wide reconciliation log
            'external_loan_id' => null,
            'provider' => $provider,
            'operation' => 'reconcile',
            'direction' => 'inbound',
            'status' => 'pending',
            'started_at' => now(),
        ]);

        try {
            // Find loans that need reconciliation:
            // - Have external_loan_id but haven't synced in > 1 hour
            // - Have sync_status = 'error'
            // - Active loans to ensure status is up-to-date
            $staleThreshold = now()->subHour();

            $loans = Loan::whereNotNull('external_loan_id')
                ->where('external_provider', $provider)
                ->where(function ($query) use ($staleThreshold) {
                    $query->where('sync_status', 'error')
                        ->orWhereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', $staleThreshold);
                })
                ->whereIn('status', ['active', 'disbursed', 'funded'])
                ->limit(100)
                ->get();

            $syncedCount = 0;
            $errors = [];

            foreach ($loans as $loan) {
                try {
                    SyncExternalLoanStatusJob::dispatch($loan->id);
                    $syncedCount++;
                } catch (\Throwable $e) {
                    $errors[] = "Loan {$loan->id}: {$e->getMessage()}";
                }
            }

            $log->update([
                'status' => 'success',
                'response_payload' => [
                    'loans_processed' => $loans->count(),
                    'synced_count' => $syncedCount,
                    'errors' => $errors,
                ],
                'completed_at' => now(),
            ]);

            Log::info("ReconcileLoansJob completed", [
                'provider' => $provider,
                'loans_processed' => $loans->count(),
                'synced_count' => $syncedCount,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ReconcileLoansJob failed", [
            'provider' => $this->provider,
            'error' => $exception->getMessage(),
        ]);
    }
}
