<?php

namespace App\Modules\Funding\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Funding\Events\FundingCompleted;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Marketplace\Services\MarketplaceService;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FundingService
{
    public function __construct(
        protected MarketplaceService $marketplaceService,
        protected LoanService $loanService,
        protected RepaymentService $repaymentService,
    ) {
    }

    // ─── Core Funding Method ─────────────────────────────────────────

    public function fund(User $lender, Loan $loan, float $amount): FundingTransaction
    {
        $this->validateFunding($lender, $loan, $amount);

        return DB::transaction(function () use ($lender, $loan, $amount) {
            // Lock the loan row for update to prevent race conditions
            $lockedLoan = Loan::lockForUpdate()->find($loan->id);

            if (! $lockedLoan) {
                throw new ApiException('Loan not found.', 404);
            }

            // Double-check loan is still fundable after lock
            if (! $lockedLoan->isOnMarketplace()) {
                throw new ApiException('This loan is no longer available for funding.', 422);
            }

            // Recalculate remaining with locked data (only confirmed funding counts)
            $remaining = $this->getRemainingFunding($lockedLoan);

            if ($amount > $remaining) {
                throw new ApiException(
                    "Cannot fund more than remaining amount. Available: {$remaining}, Requested: {$amount}",
                    422,
                );
            }

            // Create funding transaction (payment pending admin verification)
            $lenderReturnPercent = $this->lenderReturnPercent($lockedLoan);
            $transaction = FundingTransaction::create([
                'loan_id' => $lockedLoan->id,
                'lender_id' => $lender->id,
                'amount' => $amount,
                'interest_rate' => $lenderReturnPercent,
                'expected_return' => $this->calculateExpectedReturn($amount, $lockedLoan, $lenderReturnPercent),
                'status' => 'pending',
                'transaction_reference' => FundingTransaction::generateReference(),
            ]);

            // Clear marketplace cache
            $this->marketplaceService->clearListingCache($lockedLoan->id);

            return $transaction->fresh();
        });
    }

    // ─── Validation ──────────────────────────────────────────────────

    protected function validateFunding(User $lender, Loan $loan, float $amount): void
    {
        if ($amount <= 0) {
            throw new ApiException('Funding amount must be greater than 0.', 422);
        }

        $minFunding = config('loan.marketplace.min_funding_amount', 100);
        if ($amount < $minFunding) {
            throw new ApiException("Minimum funding amount is {$minFunding}.", 422);
        }

        if (! $loan->isOnMarketplace()) {
            throw new ApiException('This loan is not available for funding.', 422);
        }

        // Check if lender already funded this loan
        $existingFunding = FundingTransaction::forLoan($loan->id)
            ->forLender($lender->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->sum('amount');

        if ($existingFunding > 0) {
            throw new ApiException('You have already funded this loan.', 422);
        }

        // Check remaining funding
        $remaining = $this->getRemainingFunding($loan);
        if ($amount > $remaining) {
            throw new ApiException(
                "Cannot exceed remaining funding amount. Available: {$remaining}",
                422,
            );
        }
    }

    // ─── Status Determination ────────────────────────────────────────

    protected function determineLoanStatus(float $fundedAmount, float $targetAmount): string
    {
        if ($fundedAmount >= $targetAmount) {
            return 'funded';
        }

        if ($fundedAmount > 0) {
            return 'partially_funded';
        }

        return 'marketplace';
    }

    // ─── Expected Return Calculation ─────────────────────────────────

    protected function calculateExpectedReturn(float $amount, Loan $loan, float $lenderReturnPercent): float
    {
        return $this->loanService->expectedReturnForFunding($loan, $amount);
    }

    protected function lenderReturnPercent(Loan $loan): float
    {
        return $this->loanService->lenderReturnPercentForLoan($loan);
    }

    // ─── Remaining Funding ───────────────────────────────────────────

    public function getRemainingFunding(Loan $loan): float
    {
        return $this->loanService->remainingFunding($loan);
    }

    // ─── Submit Payment Proof ────────────────────────────────────────

    public function submitPayment(FundingTransaction $transaction, array $data, $proofFile): FundingTransaction
    {
        if (! $transaction->isPending()) {
            throw new ApiException('This funding transaction cannot be updated.', 422);
        }

        return DB::transaction(function () use ($transaction, $data, $proofFile) {
            $proofPath = $proofFile->store('funding-payments', 'public');

            $metadata = $transaction->metadata ?? [];
            $metadata['payer_reference_number'] = $data['reference_number'] ?? null;
            $metadata['payer_transaction_number'] = $data['transaction_number'] ?? null;

            $transaction->update([
                'payment_method' => $data['payment_method'],
                'payment_method_detail' => $data['payment_method_detail'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? $transaction->payment_reference,
                'payment_proof_path' => $proofPath,
                'payment_date' => $data['payment_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $metadata,
            ]);

            return $transaction->fresh();
        });
    }

    // ─── Confirm Funding ─────────────────────────────────────────────

    public function confirmFunding(
        FundingTransaction $transaction,
        ?User $admin = null,
        ?string $notes = null,
    ): FundingTransaction {
        if (! $transaction->isPending()) {
            throw new ApiException('Transaction is not in pending state.', 422);
        }

        return DB::transaction(function () use ($transaction, $admin, $notes) {
            $loan = Loan::lockForUpdate()->find($transaction->loan_id);

            if (! $loan) {
                throw new ApiException('Loan not found.', 404);
            }

            // Apply the funding to the loan
            $newFundedAmount = (float) $loan->funded_amount + (float) $transaction->amount;
            $targetAmount = (float) ($loan->approved_amount ?? $loan->requested_amount);
            $newStatus = $this->determineLoanStatus($newFundedAmount, $targetAmount);

            $loan->update([
                'funded_amount' => $newFundedAmount,
                'status' => $newStatus,
            ]);

            $updateData = [
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'admin_verified_at' => now(),
                'admin_verified_by' => $admin?->id,
                'admin_notes' => $notes ?: $transaction->admin_notes,
            ];

            $transaction->update($updateData);

            // Create investment record for the lender's portfolio
            Investment::create([
                'loan_id' => $loan->id,
                'lender_id' => $transaction->lender_id,
                'funding_transaction_id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'interest_rate' => (float) $transaction->interest_rate,
                'expected_return' => (float) $transaction->expected_return,
                'actual_return' => 0,
                'status' => 'active',
                'funded_at' => now(),
            ]);

            // Notify lender that payment was approved
            $notificationService = app(\App\Modules\Notifications\Services\NotificationService::class);
            $notificationService->queue(
                $transaction->lender,
                'funding_payment_approved',
                [
                    'loan_id' => $loan->id,
                    'reference' => $loan->reference,
                    'amount' => (float) $transaction->amount,
                    'transaction_id' => $transaction->id,
                ]
            );

            // If the loan is now fully funded, notify the borrower and all lenders.
            // Repayment schedule is created when the borrower confirms disbursement (Stage 7.1).
            if ($newStatus === 'funded') {
                FundingCompleted::dispatch($loan->id, $newFundedAmount);

                $notificationService->queue(
                    $loan->borrower,
                    'loan_funded',
                    [
                        'loan_id' => $loan->id,
                        'reference' => $loan->reference,
                        'amount' => $newFundedAmount,
                    ]
                );

                foreach ($loan->fundingTransactions()->confirmed()->with('lender')->get() as $funding) {
                    $notificationService->queue(
                        $funding->lender,
                        'loan_funded',
                        [
                            'loan_id' => $loan->id,
                            'reference' => $loan->reference,
                            'amount' => (float) $funding->amount,
                        ]
                    );
                }
            }

            // Clear marketplace cache
            $this->marketplaceService->clearListingCache($loan->id);

            return $transaction->fresh();
        });
    }

    // ─── Reject Funding ────────────────────────────────────────────────

    public function rejectFunding(
        FundingTransaction $transaction,
        ?User $admin = null,
        ?string $reason = null,
    ): FundingTransaction {
        if (! $transaction->isPending()) {
            throw new ApiException('Only pending funding transactions can be rejected.', 422);
        }

        return DB::transaction(function () use ($transaction, $admin, $reason) {
            $transaction->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'admin_verified_at' => now(),
                'admin_verified_by' => $admin?->id,
                'admin_notes' => $reason,
            ]);

            // Clear marketplace cache so the freed funding slot is reflected
            $this->marketplaceService->clearListingCache($transaction->loan_id);

            app(\App\Modules\Notifications\Services\NotificationService::class)->queue(
                $transaction->lender,
                'funding_payment_rejected',
                [
                    'loan_id' => $transaction->loan_id,
                    'reference' => $transaction->loan->reference,
                    'amount' => (float) $transaction->amount,
                    'reason' => $reason,
                    'transaction_id' => $transaction->id,
                ]
            );

            return $transaction->fresh();
        });
    }

    // ─── Request More Information ──────────────────────────────────────

    public function requestFundingInfo(
        FundingTransaction $transaction,
        ?User $admin = null,
        ?string $message = null,
    ): FundingTransaction {
        if (! $transaction->isPending()) {
            throw new ApiException('Only pending funding transactions can be marked for review.', 422);
        }

        $transaction->update([
            'admin_verified_at' => now(),
            'admin_verified_by' => $admin?->id,
            'admin_notes' => $message,
        ]);

        app(\App\Modules\Notifications\Services\NotificationService::class)->queue(
            $transaction->lender,
            'funding_payment_info_requested',
            [
                'loan_id' => $transaction->loan_id,
                'reference' => $transaction->loan->reference,
                'amount' => (float) $transaction->amount,
                'message' => $message,
                'transaction_id' => $transaction->id,
            ]
        );

        return $transaction->fresh();
    }

    // ─── Cancel Funding ──────────────────────────────────────────────

    public function cancelFunding(FundingTransaction $transaction): FundingTransaction
    {
        if (! $transaction->isPending()) {
            throw new ApiException('Only pending transactions can be cancelled.', 422);
        }

        $transaction->update(['status' => 'cancelled']);

        // Clear cache so the marketplace reflects the freed funding slot
        $this->marketplaceService->clearListingCache($transaction->loan_id);

        return $transaction->fresh();
    }

    // ─── Portfolio Queries ─────────────────────────────────────────

    public function getLenderPortfolio(User $lender, array $filters = [])
    {
        $query = Investment::forLender($lender->id)
            ->with(['loan:id,reference,approved_amount,total_repayment,status,repayment_date'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getLenderPortfolioSummary(User $lender): array
    {
        return app(EarningsService::class)->getLenderPortfolioSummary($lender);
    }

    // ─── Loan Funding Details ────────────────────────────────────────

    public function getLoanFundings(int $loanId)
    {
        return FundingTransaction::forLoan($loanId)
            ->with('lender:id,first_name,last_name')
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getLoanFundingSummary(int $loanId): array
    {
        $loan = Loan::findOrFail($loanId);
        $transactions = FundingTransaction::forLoan($loanId)->confirmed();

        return [
            'loan_id' => $loan->id,
            'reference' => $loan->reference,
            'target_amount' => (float) ($loan->approved_amount ?? $loan->requested_amount),
            'funded_amount' => (float) $loan->funded_amount,
            'remaining_amount' => $this->getRemainingFunding($loan),
            'progress_percent' => $loan->funding_progress,
            'status' => $loan->status,
            'lender_count' => $transactions->distinct('lender_id')->count('lender_id'),
            'transactions' => $transactions->count(),
        ];
    }
}
