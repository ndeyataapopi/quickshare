<?php

namespace App\Modules\Loans\DTOs;

class LoanRequestData
{
    public function __construct(
        public readonly int $borrowerId,
        public readonly float $requestedAmount,
        public readonly int $loanTermDays,
        public readonly ?string $purpose = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            borrowerId: $data['borrower_id'],
            requestedAmount: (float) $data['requested_amount'],
            loanTermDays: (int) $data['loan_term_days'],
            purpose: $data['purpose'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'borrower_id' => $this->borrowerId,
            'requested_amount' => $this->requestedAmount,
            'loan_term_days' => $this->loanTermDays,
            'purpose' => $this->purpose,
        ];
    }
}
