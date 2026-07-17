<?php

namespace App\Modules\Loans\DTOs;

class LoanRequestData
{
    public function __construct(
        public readonly int $borrowerId,
        public readonly float $requestedAmount,
        public readonly int $loanTermDays,
        public readonly ?string $purpose = null,
        public readonly bool $agreementRead = false,
        public readonly bool $agreementTermsAccepted = false,
        public readonly bool $electronicDocumentsConsented = false,
        public readonly string $agreementVersion = '',
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            borrowerId: $data['borrower_id'],
            requestedAmount: (float) $data['requested_amount'],
            loanTermDays: (int) $data['loan_term_days'],
            purpose: $data['purpose'] ?? null,
            agreementRead: (bool) ($data['agreement_read'] ?? false),
            agreementTermsAccepted: (bool) ($data['agreement_terms'] ?? false),
            electronicDocumentsConsented: (bool) ($data['electronic_documents'] ?? false),
            agreementVersion: (string) ($data['agreement_version'] ?? ''),
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'borrower_id' => $this->borrowerId,
            'requested_amount' => $this->requestedAmount,
            'loan_term_days' => $this->loanTermDays,
            'purpose' => $this->purpose,
            'agreement_read' => $this->agreementRead,
            'agreement_terms' => $this->agreementTermsAccepted,
            'electronic_documents' => $this->electronicDocumentsConsented,
            'agreement_version' => $this->agreementVersion,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }
}
