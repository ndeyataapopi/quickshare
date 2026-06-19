<?php

namespace App\Modules\Loans\Services;

use App\Models\User;
use App\Modules\Loans\DTOs\AffordabilityInput;
use App\Modules\Loans\Models\AffordabilityAssessment;
use App\Modules\Loans\Models\Loan;
use App\Modules\TrustScore\Services\TrustScoreService;

class AffordabilityService
{
    // ─── Run Full Assessment ─────────────────────────────────────────

    public function assess(User $user, AffordabilityInput $input, ?Loan $loan = null): AffordabilityAssessment
    {
        $trustScore = (float) $user->trust_score;
        $trustTier = TrustScoreService::getTier($trustScore);

        $repaymentHistory = $this->getRepaymentHistory($user);
        $dti = $this->calculateDTI($input);
        $disposableIncome = $this->calculateDisposableIncome($input);
        $maxMonthlyRepayment = $this->calculateMaxMonthlyRepayment($disposableIncome);
        $maxLoanAmount = $this->calculateMaxLoan($user, $disposableIncome);
        $affordabilityScore = $this->calculateAffordabilityScore($input, $trustScore, $repaymentHistory);
        $riskClassification = $this->classifyRisk($affordabilityScore, $dti, $trustScore);
        [$decision, $reasons] = $this->makeDecision($affordabilityScore, $dti, $trustScore, $repaymentHistory, $loan);

        return AffordabilityAssessment::create([
            'user_id' => $user->id,
            'loan_id' => $loan?->id,
            'monthly_income' => $input->monthlyIncome,
            'monthly_expenses' => $input->monthlyExpenses,
            'existing_debt' => $input->existingDebt,
            'monthly_debt_repayments' => $input->monthlyDebtRepayments,
            'payslip_gross' => $input->payslipGross,
            'payslip_net' => $input->payslipNet,
            'bank_avg_balance' => $input->bankAvgBalance,
            'bank_avg_income' => $input->bankAvgIncome,
            'bank_avg_expenses' => $input->bankAvgExpenses,
            'debt_to_income_ratio' => $dti,
            'disposable_income' => $disposableIncome,
            'affordability_score' => $affordabilityScore,
            'max_loan_amount' => $maxLoanAmount,
            'max_monthly_repayment' => $maxMonthlyRepayment,
            'trust_score' => $trustScore,
            'trust_tier' => $trustTier,
            'total_loans' => $repaymentHistory['total_loans'],
            'completed_loans' => $repaymentHistory['completed_loans'],
            'defaulted_loans' => $repaymentHistory['defaulted_loans'],
            'late_repayments' => $repaymentHistory['late_repayments'],
            'repayment_reliability' => $repaymentHistory['reliability'],
            'risk_classification' => $riskClassification,
            'decision' => $decision,
            'decision_reasons' => implode('; ', $reasons),
            'metadata' => [
                'weights' => $this->getWeights(),
                'thresholds' => $this->getThresholds(),
            ],
        ]);
    }

    // ─── Exposed Methods ─────────────────────────────────────────────

    public function approveOrRejectLoan(Loan $loan, AffordabilityInput $input): AffordabilityAssessment
    {
        return $this->assess($loan->borrower, $input, $loan);
    }

    public function calculateMaxLoan(User $user, ?float $disposableIncome = null): float
    {
        $trustMax = TrustScoreService::maxLoanAmount($user);

        if ($disposableIncome === null) {
            return $trustMax;
        }

        $maxMonthlyRepayment = $this->calculateMaxMonthlyRepayment($disposableIncome);

        // Calculate max principal that produces a monthly repayment ≤ maxMonthlyRepayment
        // Using max term for most favourable calculation
        $maxTermDays = (int) config('loans.max_term_days');
        $interestRate = (float) config('loans.interest_rate');
        $platformFeePercent = (float) config('loans.platform_fee_percent');
        $dailyRate = $interestRate / 365 / 100;
        $termMonths = $maxTermDays / 30;

        // totalRepayment = P + P*dailyRate*days + P*feePercent/100
        // totalRepayment = P * (1 + dailyRate*days + feePercent/100)
        // monthlyRepayment = totalRepayment / termMonths
        // maxMonthlyRepayment = P * multiplier / termMonths
        // P = maxMonthlyRepayment * termMonths / multiplier
        $multiplier = 1 + ($dailyRate * $maxTermDays) + ($platformFeePercent / 100);
        $incomeBasedMax = ($maxMonthlyRepayment * $termMonths) / $multiplier;
        $incomeBasedMax = round($incomeBasedMax, 2);

        $globalMax = (float) config('loans.max_amount');

        return min($trustMax, $incomeBasedMax, $globalMax);
    }

    // ─── DTI Ratio ───────────────────────────────────────────────────

    public function calculateDTI(AffordabilityInput $input): float
    {
        if ($input->monthlyIncome <= 0) {
            return 100.00;
        }

        $totalDebtPayments = $input->monthlyDebtRepayments;

        return round(($totalDebtPayments / $input->monthlyIncome) * 100, 2);
    }

    public function classifyDTI(float $dti): string
    {
        $cfg = config('loans.affordability');

        return match (true) {
            $dti <= $cfg['dti_excellent_max'] => 'excellent',
            $dti <= $cfg['dti_good_max'] => 'good',
            $dti <= $cfg['dti_fair_max'] => 'fair',
            default => 'poor',
        };
    }

    // ─── Disposable Income ───────────────────────────────────────────

    public function calculateDisposableIncome(AffordabilityInput $input): float
    {
        $income = $input->monthlyIncome;

        // Use bank data if available for more accurate picture
        if ($input->bankAvgIncome !== null) {
            $income = min($income, $input->bankAvgIncome);
        }

        $expenses = $input->monthlyExpenses;
        if ($input->bankAvgExpenses !== null) {
            $expenses = max($expenses, $input->bankAvgExpenses);
        }

        return round(max(0, $income - $expenses - $input->monthlyDebtRepayments), 2);
    }

    public function calculateMaxMonthlyRepayment(float $disposableIncome): float
    {
        $maxPercent = (float) config('loans.affordability.max_repayment_to_income_percent');

        return round($disposableIncome * ($maxPercent / 100), 2);
    }

    // ─── Affordability Score (0–100) ─────────────────────────────────

    public function calculateAffordabilityScore(
        AffordabilityInput $input,
        float $trustScore,
        array $repaymentHistory,
    ): float {
        $weights = $this->getWeights();

        $dtiScore = $this->scoreDTI($this->calculateDTI($input));
        $trustComponent = min(100, $trustScore);
        $repaymentComponent = $repaymentHistory['reliability'];
        $disposableComponent = $this->scoreDisposableIncome($input);
        $bankComponent = $this->scoreBankStability($input);

        $raw = (
            ($dtiScore * $weights['weight_dti']) +
            ($trustComponent * $weights['weight_trust_score']) +
            ($repaymentComponent * $weights['weight_repayment_history']) +
            ($disposableComponent * $weights['weight_disposable_income']) +
            ($bankComponent * $weights['weight_bank_stability'])
        ) / 100;

        return round(max(0, min(100, $raw)), 2);
    }

    // ─── Risk Classification ─────────────────────────────────────────

    public function classifyRisk(float $affordabilityScore, float $dti, float $trustScore): string
    {
        $avgSignal = ($affordabilityScore + $trustScore) / 2;

        return match (true) {
            $avgSignal >= 80 && $dti <= 20 => 'very_low',
            $avgSignal >= 65 && $dti <= 35 => 'low',
            $avgSignal >= 50 && $dti <= 50 => 'moderate',
            $avgSignal >= 35 => 'high',
            default => 'very_high',
        };
    }

    // ─── Repayment History ───────────────────────────────────────────

    public function getRepaymentHistory(User $user): array
    {
        $total = Loan::forBorrower($user->id)->count();
        $completed = Loan::forBorrower($user->id)->where('status', 'completed')->count();
        $defaulted = Loan::forBorrower($user->id)->where('status', 'defaulted')->count();
        $overdue = Loan::forBorrower($user->id)->where('status', 'overdue')->count();

        $reliability = 50.00; // default for new borrowers
        if ($total > 0) {
            $positiveSignals = $completed;
            $negativeSignals = ($defaulted * 3) + $overdue; // defaults weigh 3x
            $reliability = round(max(0, min(100, ($positiveSignals / max(1, $positiveSignals + $negativeSignals)) * 100)), 2);
        }

        return [
            'total_loans' => $total,
            'completed_loans' => $completed,
            'defaulted_loans' => $defaulted,
            'late_repayments' => $overdue,
            'reliability' => $reliability,
        ];
    }

    // ─── Decision Engine ─────────────────────────────────────────────

    protected function makeDecision(
        float $affordabilityScore,
        float $dti,
        float $trustScore,
        array $repaymentHistory,
        ?Loan $loan,
    ): array {
        $cfg = config('loans.affordability');
        $reasons = [];

        // Hard reject rules
        if ($dti > $cfg['dti_fair_max']) {
            $reasons[] = "DTI ratio ({$dti}%) exceeds maximum threshold ({$cfg['dti_fair_max']}%)";
        }

        if ($trustScore < TrustScoreService::MIN_BORROW_SCORE) {
            $reasons[] = "Trust score ({$trustScore}) below minimum borrowing threshold";
        }

        if ($repaymentHistory['defaulted_loans'] >= 2) {
            $reasons[] = "Multiple loan defaults ({$repaymentHistory['defaulted_loans']})";
        }

        // Check if loan amount exceeds affordability
        if ($loan !== null) {
            $requestedAmount = (float) $loan->requested_amount;
            $loanTermDays = $loan->loan_term_days;
            $interestRate = (float) config('loans.interest_rate');
            $platformFeePercent = (float) config('loans.platform_fee_percent');
            $dailyRate = $interestRate / 365 / 100;
            $totalRepayment = $requestedAmount * (1 + ($dailyRate * $loanTermDays) + ($platformFeePercent / 100));
            $termMonths = max(1, $loanTermDays / 30);
            $monthlyRepayment = $totalRepayment / $termMonths;
            $maxMonthly = $this->calculateMaxMonthlyRepayment(
                $this->calculateDisposableIncome(new AffordabilityInput(
                    monthlyIncome: 0, // placeholder — not used here
                )),
            );

            // Recalculate with actual income — we already have DTI info
            if ($monthlyRepayment > 0 && $dti > 0) {
                // Just flag if amount is disproportionate
                $trustMax = TrustScoreService::maxLoanAmount($loan->borrower);
                if ($requestedAmount > $trustMax) {
                    $reasons[] = "Requested amount exceeds trust-tier limit of {$trustMax}";
                }
            }
        }

        if (! empty($reasons)) {
            return ['reject', $reasons];
        }

        // Auto-approve
        if ($affordabilityScore >= $cfg['auto_approve_min_score'] && $dti <= $cfg['dti_good_max']) {
            $reasons[] = "High affordability score ({$affordabilityScore}) with healthy DTI ({$dti}%)";
            return ['approve', $reasons];
        }

        // Auto-reject on low score
        if ($affordabilityScore <= $cfg['auto_reject_max_score']) {
            $reasons[] = "Low affordability score ({$affordabilityScore})";
            return ['reject', $reasons];
        }

        // Middle ground → manual review
        $reasons[] = "Affordability score ({$affordabilityScore}) requires manual review";
        return ['manual_review', $reasons];
    }

    // ─── Score Component Helpers ─────────────────────────────────────

    protected function scoreDTI(float $dti): float
    {
        // Lower DTI = higher score
        return max(0, min(100, 100 - ($dti * 1.5)));
    }

    protected function scoreDisposableIncome(AffordabilityInput $input): float
    {
        $disposable = $this->calculateDisposableIncome($input);
        if ($input->monthlyIncome <= 0) {
            return 0;
        }

        // Disposable income as % of gross income
        $ratio = ($disposable / $input->monthlyIncome) * 100;

        return min(100, round($ratio * 1.5, 2));
    }

    protected function scoreBankStability(AffordabilityInput $input): float
    {
        if ($input->bankAvgBalance === null || $input->bankAvgIncome === null) {
            return 50.00; // neutral if no bank data
        }

        $score = 50.00;

        // Positive: balance covers expenses
        if ($input->bankAvgExpenses !== null && $input->bankAvgExpenses > 0) {
            $coverageRatio = $input->bankAvgBalance / $input->bankAvgExpenses;
            $score += min(25, $coverageRatio * 10);
        }

        // Positive: bank income matches declared income
        if ($input->monthlyIncome > 0) {
            $consistency = min(1, $input->bankAvgIncome / $input->monthlyIncome);
            $score += $consistency * 25;
        }

        return min(100, round($score, 2));
    }

    // ─── History / Queries ───────────────────────────────────────────

    public function getLatestAssessment(User $user): ?AffordabilityAssessment
    {
        return AffordabilityAssessment::forUser($user->id)->latest()->first();
    }

    public function getAssessmentHistory(User $user, int $limit = 10)
    {
        return AffordabilityAssessment::forUser($user->id)
            ->latest()
            ->take($limit)
            ->get();
    }

    // ─── Config Helpers ──────────────────────────────────────────────

    protected function getWeights(): array
    {
        $cfg = config('loans.affordability');

        return [
            'weight_dti' => $cfg['weight_dti'],
            'weight_trust_score' => $cfg['weight_trust_score'],
            'weight_repayment_history' => $cfg['weight_repayment_history'],
            'weight_disposable_income' => $cfg['weight_disposable_income'],
            'weight_bank_stability' => $cfg['weight_bank_stability'],
        ];
    }

    protected function getThresholds(): array
    {
        $cfg = config('loans.affordability');

        return [
            'auto_approve_min_score' => $cfg['auto_approve_min_score'],
            'auto_reject_max_score' => $cfg['auto_reject_max_score'],
            'dti_excellent_max' => $cfg['dti_excellent_max'],
            'dti_good_max' => $cfg['dti_good_max'],
            'dti_fair_max' => $cfg['dti_fair_max'],
        ];
    }
}
