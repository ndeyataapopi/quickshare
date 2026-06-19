<?php

namespace App\Modules\Loans\DTOs;

class AffordabilityInput
{
    public function __construct(
        public readonly float $monthlyIncome,
        public readonly float $monthlyExpenses = 0,
        public readonly float $existingDebt = 0,
        public readonly float $monthlyDebtRepayments = 0,
        public readonly ?float $payslipGross = null,
        public readonly ?float $payslipNet = null,
        public readonly ?float $bankAvgBalance = null,
        public readonly ?float $bankAvgIncome = null,
        public readonly ?float $bankAvgExpenses = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            monthlyIncome: (float) ($data['monthly_income'] ?? 0),
            monthlyExpenses: (float) ($data['monthly_expenses'] ?? 0),
            existingDebt: (float) ($data['existing_debt'] ?? 0),
            monthlyDebtRepayments: (float) ($data['monthly_debt_repayments'] ?? 0),
            payslipGross: isset($data['payslip_gross']) ? (float) $data['payslip_gross'] : null,
            payslipNet: isset($data['payslip_net']) ? (float) $data['payslip_net'] : null,
            bankAvgBalance: isset($data['bank_avg_balance']) ? (float) $data['bank_avg_balance'] : null,
            bankAvgIncome: isset($data['bank_avg_income']) ? (float) $data['bank_avg_income'] : null,
            bankAvgExpenses: isset($data['bank_avg_expenses']) ? (float) $data['bank_avg_expenses'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'monthly_income' => $this->monthlyIncome,
            'monthly_expenses' => $this->monthlyExpenses,
            'existing_debt' => $this->existingDebt,
            'monthly_debt_repayments' => $this->monthlyDebtRepayments,
            'payslip_gross' => $this->payslipGross,
            'payslip_net' => $this->payslipNet,
            'bank_avg_balance' => $this->bankAvgBalance,
            'bank_avg_income' => $this->bankAvgIncome,
            'bank_avg_expenses' => $this->bankAvgExpenses,
        ];
    }
}
