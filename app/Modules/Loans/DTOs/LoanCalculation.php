<?php

namespace App\Modules\Loans\DTOs;

class LoanCalculation
{
    public function __construct(
        public readonly float $principal,
        public readonly float $interestRate,
        public readonly int $termDays,
        public readonly float $interestAmount,
        public readonly float $platformFee,
        public readonly float $platformFeePercent,
        public readonly float $totalRepayment,
        public readonly float $dailyRate,
        public readonly float $riskScore,
        public readonly string $riskLevel,
        public readonly string $trustTier,
        public readonly float $maxAllowedAmount,
    ) {
    }

    public function toArray(): array
    {
        return [
            'principal' => round($this->principal, 2),
            'interest_rate' => round($this->interestRate, 2),
            'term_days' => $this->termDays,
            'interest_amount' => round($this->interestAmount, 2),
            'platform_fee' => round($this->platformFee, 2),
            'platform_fee_percent' => round($this->platformFeePercent, 2),
            'total_repayment' => round($this->totalRepayment, 2),
            'daily_rate' => round($this->dailyRate, 4),
            'risk_score' => round($this->riskScore, 2),
            'risk_level' => $this->riskLevel,
            'trust_tier' => $this->trustTier,
            'max_allowed_amount' => round($this->maxAllowedAmount, 2),
        ];
    }
}
