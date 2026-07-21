<?php

namespace App\Modules\Loans\Services;

use App\Exceptions\ApiException;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisbursementService
{
    const MAX_RETRIES = 3;
    const RETRY_DELAYS = [300, 900, 3600]; // 5 min, 15 min, 1 hour (in seconds)

    public function __construct(protected LoanService $loanService)
    {
    }

    // ─── Core Disbursement ───────────────────────────────────────────

    public function initiateDisbursement(Loan $loan): DisbursementTransaction
    {
        return DB::transaction(function () use ($loan) {
            // Lock loan for update
            $lockedLoan = Loan::lockForUpdate()->find($loan->id);

            if (! $lockedLoan) {
                throw new ApiException('Loan not found.', 404);
            }

            // Check if disbursement already exists (do this first to catch retries)
            $existing = DisbursementTransaction::forLoan($lockedLoan->id)
                ->whereIn('status', ['awaiting_disbursement', 'processing', 'disbursed'])
                ->first();

            if ($existing) {
                throw new ApiException(
                    'Disbursement already initiated. Status: ' . $existing->status,
                    422,
                );
            }

            // Validate loan is fully funded
            if (! $lockedLoan->isDisbursable()) {
                throw new ApiException(
                    'Loan cannot be disbursed. Status: ' . $lockedLoan->status,
                    422,
                );
            }

            // Calculate amounts
            $grossAmount = (float) $lockedLoan->funded_amount;
            $platformFee = (float) $lockedLoan->platform_fee;
            $netAmount = $this->loanService->disbursementAmount($lockedLoan);

            if ($netAmount <= 0) {
                throw new ApiException('Net disbursement amount must be positive.', 422);
            }

            // Create ledger entries
            $ledgerEntries = $this->createLedgerEntries($lockedLoan, $grossAmount, $platformFee, $netAmount);

            // Create disbursement transaction
            $transaction = DisbursementTransaction::create([
                'loan_id' => $lockedLoan->id,
                'gross_amount' => $grossAmount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'status' => 'awaiting_disbursement',
                'transaction_reference' => DisbursementTransaction::generateReference(),
                'payment_method' => 'bank_transfer',
                'ledger_entries' => $ledgerEntries,
            ]);

            // Update loan status to awaiting_disbursement
            $lockedLoan->update(['status' => 'awaiting_disbursement']);

            Log::info('Disbursement initiated', [
                'loan_id' => $lockedLoan->id,
                'disbursement_id' => $transaction->id,
                'reference' => $transaction->transaction_reference,
                'gross' => $grossAmount,
                'fee' => $platformFee,
                'net' => $netAmount,
            ]);

            return $transaction->fresh();
        });
    }

    // ─── Process Disbursement ────────────────────────────────────────

    public function processDisbursement(DisbursementTransaction $transaction): DisbursementTransaction
    {
        if (! $transaction->isAwaiting() && ! $transaction->isFailed()) {
            throw new ApiException('Disbursement cannot be processed. Status: ' . $transaction->status, 422);
        }

        $transaction->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);

        try {
            // Simulate bank/payment provider integration
            // In production, this would call an actual payment API
            $externalReference = $this->simulatePaymentTransfer($transaction);

            // Success path
            $transaction->update([
                'status' => 'disbursed',
                'external_reference' => $externalReference,
            ]);

            // Update loan status
            $loan = $transaction->loan;
            $loan->update([
                'status' => 'active',
                'disbursed_at' => now(),
            ]);

            Log::info('Disbursement successful', [
                'loan_id' => $loan->id,
                'disbursement_id' => $transaction->id,
                'external_reference' => $externalReference,
            ]);

            // Notify borrower and lenders of disbursement
            try {
                $notificationService = app(\App\Modules\Notifications\Services\NotificationService::class);
                $notificationService->sendAuto(
                    $loan->borrower,
                    'loan_disbursed',
                    [
                        'loan_id' => $loan->id,
                        'reference' => $loan->reference,
                        'amount' => $loan->approved_amount,
                        'disbursed_at' => now()->toDateString(),
                    ]
                );

                foreach ($loan->fundingTransactions()->confirmed()->with('lender')->get() as $funding) {
                    $notificationService->sendAuto(
                        $funding->lender,
                        'loan_disbursed',
                        [
                            'loan_id' => $loan->id,
                            'reference' => $loan->reference,
                            'amount' => (float) $funding->amount,
                            'disbursed_at' => now()->toDateString(),
                        ]
                    );
                }
            } catch (\Throwable $notifError) {
                Log::warning('Failed to send disbursement notification', ['error' => $notifError->getMessage()]);
            }

            return $transaction->fresh();
        } catch (\Throwable $e) {
            return $this->handleDisbursementFailure($transaction, $e);
        }
    }

    // ─── Retry Failed Disbursement ───────────────────────────────────

    public function retryDisbursement(DisbursementTransaction $transaction): DisbursementTransaction
    {
        if (! $transaction->canRetry()) {
            throw new ApiException(
                'Disbursement cannot be retried. Status: ' . $transaction->status . 
                ', Retries: ' . $transaction->retry_count,
                422,
            );
        }

        // Mark current as retried and create new transaction
        return DB::transaction(function () use ($transaction) {
            $transaction->update([
                'status' => 'retried',
                'notes' => 'Retried at ' . now()->toIso8601String(),
            ]);

            // Create new transaction with incremented retry count
            $newTransaction = DisbursementTransaction::create([
                'loan_id' => $transaction->loan_id,
                'gross_amount' => $transaction->gross_amount,
                'platform_fee' => $transaction->platform_fee,
                'net_amount' => $transaction->net_amount,
                'status' => 'awaiting_disbursement',
                'transaction_reference' => DisbursementTransaction::generateReference(),
                'payment_method' => $transaction->payment_method,
                'ledger_entries' => $transaction->ledger_entries,
                'retry_count' => $transaction->retry_count + 1,
                'notes' => 'Retry #' . ($transaction->retry_count + 1) . ' of ' . self::MAX_RETRIES,
            ]);

            Log::info('Disbursement retry initiated', [
                'original_id' => $transaction->id,
                'new_id' => $newTransaction->id,
                'loan_id' => $transaction->loan_id,
                'retry_count' => $newTransaction->retry_count,
            ]);

            return $newTransaction->fresh();
        });
    }

    // ─── Handle Failure ──────────────────────────────────────────────

    protected function handleDisbursementFailure(
        DisbursementTransaction $transaction,
        \Throwable $e,
    ): DisbursementTransaction {
        $retryCount = $transaction->retry_count;
        $canRetry = $retryCount < self::MAX_RETRIES;

        $updateData = [
            'status' => 'failed',
            'failure_reason' => $e->getMessage(),
        ];

        if ($canRetry) {
            $delay = self::RETRY_DELAYS[$retryCount] ?? 3600;
            $updateData['next_retry_at'] = now()->addSeconds($delay);
        }

        $transaction->update($updateData);

        Log::error('Disbursement failed', [
            'loan_id' => $transaction->loan_id,
            'disbursement_id' => $transaction->id,
            'error' => $e->getMessage(),
            'retry_count' => $retryCount,
            'will_retry' => $canRetry,
            'next_retry_at' => $updateData['next_retry_at'] ?? null,
        ]);

        return $transaction->fresh();
    }

    // ─── Simulate Payment (Production: Replace with Real API) ────────

    protected function simulatePaymentTransfer(DisbursementTransaction $transaction): string
    {
        // In production, this would:
        // 1. Call bank/payment provider API
        // 2. Handle 2FA, OTP, etc.
        // 3. Return external reference from provider
        
        // Simulate processing delay
        usleep(100000); // 100ms
        
        // Generate mock external reference
        return 'BNK-' . strtoupper(bin2hex(random_bytes(8)));
    }

    // ─── Ledger Creation ─────────────────────────────────────────────

    protected function createLedgerEntries(
        Loan $loan,
        float $gross,
        float $fee,
        float $net,
    ): array {
        return [
            [
                'account' => 'loan_funding_receivable',
                'debit' => $gross,
                'credit' => 0,
                'description' => 'Loan funding from lenders',
                'reference' => $loan->reference,
            ],
            [
                'account' => 'platform_fee_income',
                'debit' => 0,
                'credit' => $fee,
                'description' => 'Platform fee deducted',
                'reference' => $loan->reference,
            ],
            [
                'account' => 'loan_disbursement_payable',
                'debit' => 0,
                'credit' => $net,
                'description' => 'Net amount to borrower',
                'reference' => $loan->reference,
            ],
            [
                'account' => 'loan_receivable',
                'debit' => $loan->total_repayment,
                'credit' => 0,
                'description' => 'Total repayment due from borrower',
                'reference' => $loan->reference,
            ],
        ];
    }

    // ─── Reconciliation ──────────────────────────────────────────────

    public function reconcile(
        DisbursementTransaction $transaction,
        string $reconciledBy,
        ?array $data = null,
    ): DisbursementTransaction {
        if (! $transaction->isDisbursed()) {
            throw new ApiException('Only disbursed transactions can be reconciled.', 422);
        }

        $transaction->update([
            'reconciled_at' => now(),
            'reconciled_by' => $reconciledBy,
            'reconciliation_data' => $data,
        ]);

        Log::info('Disbursement reconciled', [
            'disbursement_id' => $transaction->id,
            'loan_id' => $transaction->loan_id,
            'reconciled_by' => $reconciledBy,
        ]);

        return $transaction->fresh();
    }

    // ─── Queries ─────────────────────────────────────────────────────

    public function getDisbursement(int $id): ?DisbursementTransaction
    {
        return DisbursementTransaction::with('loan')->find($id);
    }

    public function getLoanDisbursements(int $loanId)
    {
        return DisbursementTransaction::forLoan($loanId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPendingDisbursements()
    {
        return DisbursementTransaction::pendingProcessing()
            ->with('loan')
            ->limit(50)
            ->get();
    }

    public function getFailedDisbursementsNeedingRetry()
    {
        return DisbursementTransaction::needsRetry()
            ->with('loan')
            ->get();
    }

    public function getReconciliationReport(): array
    {
        $disbursed = DisbursementTransaction::disbursed();
        $reconciled = (clone $disbursed)->whereNotNull('reconciled_at');
        $unreconciled = (clone $disbursed)->whereNull('reconciled_at');

        return [
            'total_disbursed' => (clone $disbursed)->count(),
            'total_amount' => round((clone $disbursed)->sum('net_amount'), 2),
            'reconciled_count' => $reconciled->count(),
            'reconciled_amount' => round($reconciled->sum('net_amount'), 2),
            'unreconciled_count' => $unreconciled->count(),
            'unreconciled_amount' => round($unreconciled->sum('net_amount'), 2),
            'failed_count' => DisbursementTransaction::failed()->count(),
            'awaiting_count' => DisbursementTransaction::awaiting()->count(),
        ];
    }
}
