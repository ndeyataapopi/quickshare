<?php

namespace App\Modules\Funding\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Funding\Events\FundingCompleted;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\TrustTierService;
use App\Modules\Marketplace\Services\MarketplaceService;
use Illuminate\Support\Facades\DB;

class FundingService
{
    public function __construct(
        protected MarketplaceService $marketplaceService,
        protected TrustTierService $trustTierService,
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

        $minFunding = config('loans.min_funding_amount', 100);
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
        $termDays = $loan->loan_term_days;
        $dailyRate = $lenderReturnPercent / 365 / 100;

        // Simple interest calculation for lender's portion
        $interest = round($amount * $dailyRate * $termDays, 2);

        return round($amount + $interest, 2);
    }

    protected function lenderReturnPercent(Loan $loan): float
    {
        $score = (float) $loan->risk_score;

        return $this->trustTierService->forScore($score)['lender_return_percent'];
    }

    // ─── Remaining Funding ───────────────────────────────────────────

    public function getRemainingFunding(Loan $loan): float
    {
        $target = (float) ($loan->approved_amount ?? $loan->requested_amount);
        $funded = (float) $loan->funded_amount;

        return max(0, round($target - $funded, 2));
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

            // If the loan is now fully funded, notify the borrower and all lenders
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

        $transaction->update([
            'status' => 'cancelled',
            'admin_verified_at' => now(),
            'admin_verified_by' => $admin?->id,
            'admin_notes' => $reason,
        ]);

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
        $query = FundingTransaction::forLender($lender->id)
            ->with(['loan:id,reference,approved_amount,total_repayment,status,repayment_date'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getLenderPortfolioSummary(User $lender): array
    {
        $confirmed = FundingTransaction::forLender($lender->id)->confirmed();

        return [
            'total_invested' => round((float) (clone $confirmed)->sum('amount'), 2),
            'total_expected_return' => round((float) (clone $confirmed)->sum('expected_return'), 2),
            'active_investments' => (clone $confirmed)
                ->whereHas('loan', fn ($q) => $q->whereIn('loans.status', ['funded', 'active', 'disbursed']))
                ->count(),
            'completed_investments' => (clone $confirmed)
                ->whereHas('loan', fn ($q) => $q->where('loans.status', 'completed'))
                ->count(),
            'defaulted_investments' => (clone $confirmed)
                ->whereHas('loan', fn ($q) => $q->where('loans.status', 'defaulted'))
                ->count(),
            'pending_transactions' => FundingTransaction::forLender($lender->id)
                ->where('status', 'pending')
                ->count(),
        ];
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
