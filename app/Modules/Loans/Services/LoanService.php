<?php

namespace App\Modules\Loans\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Loans\DTOs\LoanCalculation;
use App\Modules\Loans\DTOs\LoanRequestData;
use App\Modules\Loans\Events\LoanApproved;
use App\Modules\Loans\Events\LoanRejected;
use App\Modules\Loans\Events\LoanRequested;
use App\Modules\Loans\Models\Loan;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(protected TrustScoreService $trustScoreService)
    {
    }

    // ─── Loan Calculation ────────────────────────────────────────────

    public function calculate(User $borrower, float $amount, int $termDays): LoanCalculation
    {
        $interestRate = (float) config('loans.interest_rate');
        $platformFeePercent = (float) config('loans.platform_fee_percent');
        $riskScore = (float) $borrower->trust_score;
        $riskLevel = $borrower->riskLevel();
        $trustTier = $borrower->trust_tier;
        $maxAllowed = TrustScoreService::maxLoanAmount($borrower);

        // Calculate interest as flat percentage of loan amount
        $interestAmount = round($amount * ($interestRate / 100), 2);
        $platformFee = round($amount * ($platformFeePercent / 100), 2);
        $totalRepayment = round($amount + $interestAmount + $platformFee, 2);
        $dailyRate = $totalRepayment / $termDays;

        return new LoanCalculation(
            principal: $amount,
            interestRate: $interestRate,
            termDays: $termDays,
            interestAmount: $interestAmount,
            platformFee: $platformFee,
            platformFeePercent: $platformFeePercent,
            totalRepayment: $totalRepayment,
            dailyRate: $dailyRate,
            riskScore: $riskScore,
            riskLevel: $riskLevel,
            trustTier: $trustTier,
            maxAllowedAmount: $maxAllowed,
        );
    }

    // ─── Create Loan Request ─────────────────────────────────────────

    public function createLoan(User $borrower, array $data): Loan
    {
        $this->validateEligibility($borrower, $data['amount']);
        $this->validateTermDays($data['repayment_period']);
        $this->validateAmount($data['amount'], $borrower);
        $this->validateActiveLoansLimit($borrower);

        $calculation = $this->calculate($borrower, $data['amount'], $data['repayment_period']);

        return DB::transaction(function () use ($borrower, $data, $calculation) {
            $loan = Loan::create([
                'borrower_id' => $borrower->id,
                'reference' => Loan::generateReference(),
                'amount' => $data['amount'],
                'requested_amount' => $data['amount'],
                'interest_rate' => $calculation->interestRate,
                'platform_fee' => $calculation->platformFee,
                'total_repayment' => $calculation->totalRepayment,
                'loan_term_days' => $data['repayment_period'],
                'purpose' => $data['purpose'],
                'description' => $data['description'] ?? null,
                'risk_score' => $calculation->riskScore,
                'status' => 'pending_review',
                'submitted_at' => now(),
            ]);

            event(new LoanRequested($borrower, $data['amount'], $data['repayment_period']));

            return $loan;
        });
    }

    public function requestLoan(LoanRequestData $data): Loan
    {
        $borrower = User::findOrFail($data->borrowerId);

        $this->validateEligibility($borrower, $data->requestedAmount);
        $this->validateTermDays($data->loanTermDays);
        $this->validateAmount($data->requestedAmount, $borrower);
        $this->validateActiveLoansLimit($borrower);

        $calculation = $this->calculate($borrower, $data->requestedAmount, $data->loanTermDays);

        return DB::transaction(function () use ($borrower, $data, $calculation) {
            $loan = Loan::create([
                'borrower_id' => $borrower->id,
                'reference' => Loan::generateReference(),
                'requested_amount' => $data->requestedAmount,
                'interest_rate' => $calculation->interestRate,
                'platform_fee' => $calculation->platformFee,
                'total_repayment' => $calculation->totalRepayment,
                'loan_term_days' => $data->loanTermDays,
                'risk_score' => $calculation->riskScore,
                'status' => 'pending_review',
                'submitted_at' => now(),
            ]);

            event(new LoanRequested($borrower, $data->requestedAmount, $data->loanTermDays));

            return $loan;
        });
    }

    // ─── Admin: Approve ──────────────────────────────────────────────

    public function approve(Loan $loan, User $reviewer, ?float $approvedAmount = null, ?string $notes = null): Loan
    {
        if (! $loan->isApprovable()) {
            throw new ApiException('This loan cannot be approved in its current state.', 422);
        }

        $amount = $approvedAmount ?? (float) $loan->requested_amount;

        // Recalculate with approved amount
        $borrower = $loan->borrower;
        $calculation = $this->calculate($borrower, $amount, $loan->loan_term_days);

        return DB::transaction(function () use ($loan, $reviewer, $amount, $calculation, $notes) {
            $repaymentDate = now()->addDays($loan->loan_term_days)->toDateString();

            $loan->update([
                'approved_amount' => $amount,
                'interest_rate' => $calculation->interestRate,
                'platform_fee' => $calculation->platformFee,
                'total_repayment' => $calculation->totalRepayment,
                'risk_score' => $calculation->riskScore,
                'repayment_date' => $repaymentDate,
                'status' => 'marketplace',
                'reviewed_by' => $reviewer->id,
                'admin_notes' => $notes,
                'approved_at' => now(),
            ]);

            event(new LoanApproved($loan->id, $loan->borrower));

            return $loan->fresh();
        });
    }

    // ─── Admin: Reject ───────────────────────────────────────────────

    public function reject(Loan $loan, User $reviewer, string $reason): Loan
    {
        if (! $loan->isApprovable()) {
            throw new ApiException('This loan cannot be rejected in its current state.', 422);
        }

        return DB::transaction(function () use ($loan, $reviewer, $reason) {
            $loan->update([
                'status' => 'cancelled',
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $reason,
            ]);

            event(new LoanRejected($loan->id, $loan->borrower, $reason));

            return $loan->fresh();
        });
    }

    // ─── Borrower: Cancel ────────────────────────────────────────────

    public function cancel(Loan $loan, User $borrower): Loan
    {
        if (! $loan->isCancellable()) {
            throw new ApiException('This loan cannot be cancelled.', 422);
        }

        if ($loan->borrower_id !== $borrower->id) {
            throw new ApiException('Unauthorized.', 403);
        }

        $loan->update(['status' => 'cancelled']);

        return $loan->fresh();
    }

    // ─── Queries ─────────────────────────────────────────────────────

    public function getBorrowerLoans(User $borrower)
    {
        return Loan::forBorrower($borrower->id)
            ->latest()
            ->paginate(20);
    }

    public function getPendingReviewLoans()
    {
        return Loan::pendingReview()
            ->with('borrower:id,first_name,last_name,email,trust_score')
            ->latest('submitted_at')
            ->paginate(20);
    }

    public function getMarketplaceLoans()
    {
        return Loan::onMarketplace()
            ->with('borrower:id,first_name,last_name,trust_score')
            ->latest('approved_at')
            ->paginate(20);
    }

    // ─── Validation ──────────────────────────────────────────────────

    protected function validateEligibility(User $borrower, float $amount): void
    {
        if (! $borrower->isActive()) {
            throw new ApiException('Your account is not active.', 422);
        }

        if (! $borrower->canBorrow()) {
            throw new ApiException(
                'Your trust score is too low to borrow. Minimum required: ' . TrustScoreService::MIN_BORROW_SCORE,
                422,
            );
        }
    }

    protected function validateAmount(float $amount, User $borrower): void
    {
        $min = (float) config('loans.min_amount');
        $globalMax = (float) config('loans.max_amount');
        $trustMax = TrustScoreService::maxLoanAmount($borrower);
        $effectiveMax = min($globalMax, $trustMax);

        if ($amount < $min) {
            throw new ApiException("Minimum loan amount is {$min}.", 422);
        }

        if ($amount > $effectiveMax) {
            throw new ApiException(
                "Maximum loan amount for your trust tier is {$effectiveMax}.",
                422,
            );
        }
    }

    protected function validateTermDays(int $days): void
    {
        $min = (int) config('loans.min_term_days');
        $max = (int) config('loans.max_term_days');

        if ($days < $min || $days > $max) {
            throw new ApiException("Loan term must be between {$min} and {$max} days.", 422);
        }
    }

    protected function validateActiveLoansLimit(User $borrower): void
    {
        $maxActive = (int) config('loans.max_active_loans');
        $activeCount = Loan::forBorrower($borrower->id)
            ->whereIn('status', ['pending_review', 'marketplace', 'partially_funded', 'funded', 'disbursed', 'active'])
            ->count();

        if ($activeCount >= $maxActive) {
            throw new ApiException("You have reached the maximum of {$maxActive} active loans.", 422);
        }
    }
}
