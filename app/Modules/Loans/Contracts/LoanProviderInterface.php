<?php

namespace App\Modules\Loans\Contracts;

use App\Modules\Loans\Models\Loan;

interface LoanProviderInterface
{
    /**
     * Create a loan in the external provider.
     */
    public function createLoan(Loan $loan): array;

    /**
     * Update an existing loan in the external provider.
     */
    public function updateLoan(Loan $loan): array;

    /**
     * Get the current status of a loan from the external provider.
     */
    public function getLoanStatus(string $externalLoanId): array;

    /**
     * Approve a loan in the external provider.
     */
    public function approveLoan(Loan $loan): array;

    /**
     * Reject a loan in the external provider.
     */
    public function rejectLoan(Loan $loan, string $reason): array;

    /**
     * Disburse a loan in the external provider.
     */
    public function disburseLoan(Loan $loan): array;

    /**
     * Record a repayment in the external provider.
     */
    public function recordRepayment(Loan $loan, float $amount, array $metadata = []): array;

    /**
     * Get the provider name (e.g., 'mifos').
     */
    public function getProviderName(): string;

    /**
     * Check if the provider is healthy/configured.
     */
    public function isHealthy(): bool;
}
