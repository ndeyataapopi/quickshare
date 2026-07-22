<?php

namespace App\Modules\Loans\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\DTOs\LoanCalculation;
use App\Modules\Loans\DTOs\LoanRequestData;
use App\Modules\Loans\Events\LoanApproved;
use App\Modules\Loans\Events\LoanRejected;
use App\Modules\Loans\Events\LoanRequested;
use App\Modules\Loans\Jobs\GenerateLoanAgreementJob;
use App\Modules\Loans\Mail\LoanAgreementMail;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class LoanService
{
    public function __construct(
        protected TrustScoreService $trustScoreService,
        protected TrustTierService $trustTierService,
        protected LoanAgreementService $loanAgreementService,
    ) {}

    // ─── Loan Calculation ────────────────────────────────────────────

    public function calculate(User $borrower, float $amount, int $termDays): LoanCalculation
    {
        $termDays = (int) $termDays;
        $riskScore = (float) $borrower->trust_score;
        $tier = $this->trustTierService->forScore($riskScore);
        $platformFeePercent = $tier['platform_fee_percent'];
        $lenderReturnPercent = $tier['lender_return_percent'];
        $interestRate = $platformFeePercent + $lenderReturnPercent;
        $riskLevel = $borrower->riskLevel();
        $trustTier = $tier['name'];
        $maxAllowed = $tier['maximum_loan'];

        // Calculate platform fee and lender return as flat percentages of principal
        $platformFee = round($amount * ($platformFeePercent / 100), 2);
        $lenderReturnAmount = round($amount * ($lenderReturnPercent / 100), 2);
        $interestAmount = round($platformFee + $lenderReturnAmount, 2);
        $totalRepayment = round($amount + $interestAmount, 2);
        $dailyRate = $totalRepayment / $termDays;
        $repaymentDate = now()->copy()->addDays($termDays)->toDateString();

        return new LoanCalculation(
            principal: $amount,
            interestRate: $interestRate,
            termDays: $termDays,
            interestAmount: $interestAmount,
            platformFee: $platformFee,
            platformFeePercent: $platformFeePercent,
            lenderReturnPercent: $lenderReturnPercent,
            lenderReturnAmount: $lenderReturnAmount,
            totalRepayment: $totalRepayment,
            dailyRate: $dailyRate,
            riskScore: $riskScore,
            riskLevel: $riskLevel,
            trustTier: $trustTier,
            maxAllowedAmount: $maxAllowed,
            repaymentDate: $repaymentDate,
        );
    }

    // ─── Create Loan Request ─────────────────────────────────────────

    public function createLoan(User $borrower, array $data): Loan
    {
        $this->validateEligibility($borrower, $data['amount']);
        $this->validateTermDays($data['repayment_period'], $borrower);
        $this->validateAmount($data['amount'], $borrower);
        $this->validateActiveLoansLimit($borrower);
        $tier = $this->trustTierService->forScore((float) $borrower->trust_score);
        $this->validateConfiguration($tier);
        $this->validateAgreement(
            (bool) ($data['agreement_read'] ?? false),
            (bool) ($data['agreement_terms'] ?? false),
            (bool) ($data['electronic_documents'] ?? false),
            (string) ($data['agreement_version'] ?? ''),
        );

        $termDays = (int) $data['repayment_period'];
        $calculation = $this->calculate($borrower, $data['amount'], $termDays);
        $submittedAt = now();
        $repaymentDate = $submittedAt->copy()->addDays($termDays);
        $configurationSnapshot = $this->configurationSnapshot($tier, $calculation);
        $consent = $this->agreementConsent();

        $loan = DB::transaction(function () use ($borrower, $data, $calculation, $submittedAt, $repaymentDate, $configurationSnapshot, $consent) {
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
                'repayment_date' => $repaymentDate->toDateString(),
                'agreement_version' => config('loan.agreement.version'),
                'configuration_snapshot' => $configurationSnapshot,
                'agreement_consent' => $consent,
                'agreement_ip_address' => $data['ip_address'] ?? null,
                'agreement_user_agent' => $data['user_agent'] ?? null,
                'agreement_consented_at' => $submittedAt,
                'status' => 'pending_review',
                'submitted_at' => $submittedAt,
            ]);

            event(new LoanRequested($borrower, $data['amount'], $data['repayment_period'], $loan->id));

            return $loan;
        });

        GenerateLoanAgreementJob::dispatch($loan->id);
        $loan->refresh();
        $this->queueLoanAgreementEmail($loan);

        return $loan;
    }

    public function requestLoan(LoanRequestData $data): Loan
    {
        $borrower = User::findOrFail($data->borrowerId);

        $this->validateEligibility($borrower, $data->requestedAmount);
        $this->validateTermDays($data->loanTermDays, $borrower);
        $this->validateAmount($data->requestedAmount, $borrower);
        $this->validateActiveLoansLimit($borrower);
        $tier = $this->trustTierService->forScore((float) $borrower->trust_score);
        $this->validateConfiguration($tier);
        $this->validateAgreement(
            $data->agreementRead,
            $data->agreementTermsAccepted,
            $data->electronicDocumentsConsented,
            $data->agreementVersion,
        );

        $termDays = (int) $data->loanTermDays;
        $calculation = $this->calculate($borrower, $data->requestedAmount, $termDays);
        $submittedAt = now();
        $repaymentDate = $submittedAt->copy()->addDays($termDays);
        $configurationSnapshot = $this->configurationSnapshot($tier, $calculation);
        $consent = $this->agreementConsent();

        $loan = DB::transaction(function () use ($borrower, $data, $calculation, $submittedAt, $repaymentDate, $configurationSnapshot, $consent) {
            $loan = Loan::create([
                'borrower_id' => $borrower->id,
                'reference' => Loan::generateReference(),
                'requested_amount' => $data->requestedAmount,
                'interest_rate' => $calculation->interestRate,
                'platform_fee' => $calculation->platformFee,
                'total_repayment' => $calculation->totalRepayment,
                'loan_term_days' => $data->loanTermDays,
                'purpose' => $data->purpose,
                'risk_score' => $calculation->riskScore,
                'repayment_date' => $repaymentDate->toDateString(),
                'agreement_version' => config('loan.agreement.version'),
                'configuration_snapshot' => $configurationSnapshot,
                'agreement_consent' => $consent,
                'agreement_ip_address' => $data->ipAddress,
                'agreement_user_agent' => $data->userAgent,
                'agreement_consented_at' => $submittedAt,
                'status' => 'pending_review',
                'submitted_at' => $submittedAt,
            ]);

            event(new LoanRequested($borrower, $data->requestedAmount, $data->loanTermDays, $loan->id));

            return $loan;
        });

        GenerateLoanAgreementJob::dispatch($loan->id);
        $loan->refresh();
        $this->queueLoanAgreementEmail($loan);

        return $loan;
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

        $updated = DB::transaction(function () use ($loan, $reviewer, $amount, $calculation, $notes) {
            $repaymentDate = now()->addDays($loan->loan_term_days);

            $loan->update([
                'approved_amount' => $amount,
                'interest_rate' => $calculation->interestRate,
                'platform_fee' => $calculation->platformFee,
                'total_repayment' => $calculation->totalRepayment,
                'risk_score' => $calculation->riskScore,
                'repayment_date' => $repaymentDate->toDateString(),
                'status' => 'marketplace',
                'reviewed_by' => $reviewer->id,
                'admin_notes' => $notes,
                'approved_at' => now(),
            ]);

            event(new LoanApproved($loan->id, $loan->borrower));

            return $loan->fresh();
        });

        if ($updated->agreement_path === null) {
            GenerateLoanAgreementJob::dispatch($updated->id);
            $updated->refresh();
        }

        return $updated;
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
                'rejected_at' => now(),
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

    protected function queueLoanAgreementEmail(Loan $loan): void
    {
        Mail::to($loan->borrower->email)->queue(new LoanAgreementMail($loan));
    }

    protected function validateEligibility(User $borrower, float $amount): void
    {
        if (! $borrower->isActive()) {
            throw new ApiException('Your account is not active.', 422);
        }

        $kycSubmission = $borrower->kycSubmission;
        if (! $kycSubmission || ! $kycSubmission->isApproved()) {
            throw new ApiException('You must complete KYC verification before requesting a loan.', 422);
        }

        if (! $borrower->canBorrow()) {
            throw new ApiException(
                'Your trust score is too low to borrow. Minimum required: '.$this->trustTierService->minimumBorrowScore(),
                422,
            );
        }
    }

    protected function validateAmount(float $amount, User $borrower): void
    {
        $min = (float) config('loan.loan_limits.min_amount');
        $effectiveMax = TrustScoreService::maxLoanAmount($borrower);

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

    protected function validateTermDays(int $days, User $borrower): void
    {
        $durations = $this->trustTierService->forScore((float) $borrower->trust_score)['allowed_durations'];

        if (! in_array($days, $durations, true)) {
            throw new ApiException(
                'Loan term must be one of: '.implode(', ', $durations).' days.',
                422,
            );
        }
    }

    protected function validateAgreement(
        bool $agreementRead,
        bool $agreementTermsAccepted,
        bool $electronicDocumentsConsented,
        string $agreementVersion,
    ): void {
        if (! $agreementRead || ! $agreementTermsAccepted || ! $electronicDocumentsConsented) {
            throw new ApiException('You must read and accept the loan agreement and consent to electronic documents.', 422);
        }

        if (! hash_equals((string) config('loan.agreement.version'), $agreementVersion)) {
            throw new ApiException('The loan agreement has changed. Please review the current agreement before submitting.', 422);
        }
    }

    protected function validateConfiguration(array $tier): void
    {
        if (
            $tier['maximum_loan'] <= 0
            || $tier['total_charge_percent'] < 0
            || $tier['platform_fee_percent'] < 0
            || $tier['lender_return_percent'] < 0
            || (float) config('loan.loan_limits.min_amount') <= 0
            || (string) config('loan.agreement.version') === ''
            || (string) config('loan.agreement.terms') === ''
            || (string) config('loan.agreement.conditions') === ''
        ) {
            throw new RuntimeException('Loan configuration is invalid.');
        }
    }

    protected function configurationSnapshot(array $tier, LoanCalculation $calculation): array
    {
        return [
            'currency' => config('loan.general.currency'),
            'currency_symbol' => config('loan.general.currency_symbol'),
            'minimum_amount' => (float) config('loan.loan_limits.min_amount'),
            'maximum_active_loans' => (int) config('loan.loan_limits.max_active_loans'),
            'minimum_borrow_score' => $this->trustTierService->minimumBorrowScore(),
            'trust_tier' => $tier,
            'calculation' => $calculation->toArray(),
            'agreement' => [
                'version' => (string) config('loan.agreement.version'),
                'terms' => (string) config('loan.agreement.terms'),
                'conditions' => (string) config('loan.agreement.conditions'),
            ],
        ];
    }

    protected function agreementConsent(): array
    {
        return [
            'agreement_read' => true,
            'agreement_terms_accepted' => true,
            'electronic_documents_consented' => true,
        ];
    }

    protected function validateActiveLoansLimit(User $borrower): void
    {
        $maxActive = (int) config('loan.loan_limits.max_active_loans');
        $activeCount = Loan::forBorrower($borrower->id)
            ->whereIn('status', ['pending_review', 'marketplace', 'partially_funded', 'funded', 'disbursed', 'active'])
            ->count();

        if ($activeCount >= $maxActive) {
            throw new ApiException("You have reached the maximum of {$maxActive} active loans.", 422);
        }
    }

    // ─── Financial Engine ────────────────────────────────────────────

    public function fundingCalculation(Loan $loan, float $investmentAmount): array
    {
        $target = $this->loanPrincipal($loan);
        $lenderReturnPool = $this->loanLenderReturnAmount($loan);

        $share = $target > 0 ? $investmentAmount / $target : 0;
        $expectedProfit = round($lenderReturnPool * $share, 2);
        $expectedReturn = round($investmentAmount + $expectedProfit, 2);
        $fundingPercentage = $this->fundingPercentage($loan, $investmentAmount);

        return [
            'investment_amount' => $investmentAmount,
            'lender_return_percent' => $this->lenderReturnPercentForLoan($loan),
            'lender_return_pool' => $lenderReturnPool,
            'expected_profit' => $expectedProfit,
            'expected_return' => $expectedReturn,
            'funding_percentage' => $fundingPercentage,
        ];
    }

    public function expectedReturnForFunding(Loan $loan, float $investmentAmount): float
    {
        return $this->fundingCalculation($loan, $investmentAmount)['expected_return'];
    }

    public function expectedProfitForFunding(Loan $loan, float $investmentAmount): float
    {
        return $this->fundingCalculation($loan, $investmentAmount)['expected_profit'];
    }

    public function repaymentCalculation(Loan $loan): array
    {
        $principal = $this->loanPrincipal($loan);
        $platformFee = (float) $loan->platform_fee;
        $lenderReturnAmount = $this->loanLenderReturnAmount($loan);
        $totalRepayment = (float) $loan->total_repayment;

        return [
            'amount' => $totalRepayment,
            'principal' => $principal,
            'platform_fee' => $platformFee,
            'lender_return' => $lenderReturnAmount,
            'due_date' => $loan->repayment_date?->toDateString()
                ?? now()->addDays($loan->loan_term_days)->toDateString(),
        ];
    }

    public function lenderRepaymentDistribution(Loan $loan, FundingTransaction $funding, float $totalFunded, float $paymentAmount): array
    {
        $proportion = $totalFunded > 0 ? $funding->amount / $totalFunded : 0;

        $repayment = $this->repaymentCalculation($loan);
        $principal = $repayment['principal'];
        $lenderReturn = $repayment['lender_return'];
        $totalRepayment = $repayment['amount'];

        if ($totalRepayment <= 0 || $principal <= 0) {
            return [
                'amount' => 0,
                'principal_return' => 0,
                'interest_earned' => 0,
                'funding_percentage' => 0,
            ];
        }

        // Lenders are entitled to principal + their return only; platform fee stays with the platform.
        $lendersShare = round($paymentAmount * (($principal + $lenderReturn) / $totalRepayment), 2);
        $lenderAmount = round($lendersShare * $proportion, 2);

        $lenderPool = $principal + $lenderReturn;
        $principalReturn = round($lenderAmount * ($principal / $lenderPool), 2);
        $interestEarned = round($lenderAmount - $principalReturn, 2);

        return [
            'amount' => $lenderAmount,
            'principal_return' => $principalReturn,
            'interest_earned' => $interestEarned,
            'funding_percentage' => round($proportion * 100, 2),
        ];
    }

    public function outstandingBalance(float $totalDue, float $alreadyPaid, float $penalty = 0): float
    {
        return max(0, round($totalDue + $penalty - $alreadyPaid, 2));
    }

    public function penaltyForRepayment(Repayment $repayment, int $daysOverdue): float
    {
        $rate = (float) config('loan.repayment.penalty_rate_weekly', 0.05);
        $maxRatio = (float) config('loan.repayment.max_penalty_ratio', 0.50);

        $weeksOverdue = ceil($daysOverdue / 7);
        $penalty = $repayment->amount * ($rate * $weeksOverdue);
        $maxPenalty = $repayment->amount * $maxRatio;

        return round(min($penalty, $maxPenalty), 2);
    }

    public function disbursementAmount(Loan $loan): float
    {
        return $this->loanPrincipal($loan);
    }

    public function affordabilityMaxLoan(User $user, float $disposableIncome): float
    {
        $tier = $this->trustTierService->forScore((float) $user->trust_score);
        $trustMax = $tier['maximum_loan'];

        if ($disposableIncome <= 0) {
            return 0.00;
        }

        $maxMonthlyRepayment = $this->maxMonthlyRepayment($disposableIncome);
        $maxTermDays = max($tier['allowed_durations']);
        $termMonths = max(1, $maxTermDays / 30);
        $totalChargePercent = $tier['total_charge_percent'];

        $multiplier = 1 + ($totalChargePercent / 100);
        $incomeBasedMax = ($maxMonthlyRepayment * $termMonths) / $multiplier;

        return min($trustMax, round($incomeBasedMax, 2));
    }

    public function totalRepaymentFor(float $principal, float $totalChargePercent): float
    {
        return round($principal * (1 + ($totalChargePercent / 100)), 2);
    }

    public function fundingProgress(Loan $loan): float
    {
        $target = $this->loanPrincipal($loan);
        if ($target <= 0) {
            return 0;
        }

        return round(((float) $loan->funded_amount / $target) * 100, 2);
    }

    public function remainingFunding(Loan $loan): float
    {
        return max(0, round($this->loanPrincipal($loan) - (float) $loan->funded_amount, 2));
    }

    public function fundingPercentage(Loan $loan, float $amount): float
    {
        $target = $this->loanPrincipal($loan);
        if ($target <= 0) {
            return 0;
        }

        return round(($amount / $target) * 100, 2);
    }

    public function loanLenderReturnAmount(Loan $loan): float
    {
        return round((float) $loan->total_repayment - (float) $loan->platform_fee - $this->loanPrincipal($loan), 2);
    }

    public function loanPrincipal(Loan $loan): float
    {
        return (float) ($loan->approved_amount ?? $loan->requested_amount);
    }

    public function lenderReturnPercentForLoan(Loan $loan): float
    {
        return $this->lenderReturnPercentForScore((float) $loan->risk_score);
    }

    public function lenderReturnPercentForScore(float $score): float
    {
        return $this->trustTierService->forScore($score)['lender_return_percent'];
    }

    public function platformFeePercentForScore(float $score): float
    {
        return $this->trustTierService->forScore($score)['platform_fee_percent'];
    }

    public function totalChargePercentForScore(float $score): float
    {
        return $this->trustTierService->forScore($score)['total_charge_percent'];
    }

    public function maxLoanForUser(User $user): float
    {
        return TrustScoreService::maxLoanAmount($user);
    }

    public function maxMonthlyRepayment(float $disposableIncome): float
    {
        $maxPercent = (float) config('loan.affordability.max_repayment_to_income_percent', 30.00);

        return round($disposableIncome * ($maxPercent / 100), 2);
    }
}
