<?php

namespace App\Modules\Loans\Adapters;

use App\Modules\Loans\Contracts\LoanProviderInterface;
use App\Modules\Loans\Models\Loan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MifosAdapter implements LoanProviderInterface
{
    protected string $baseUrl;
    protected string $tenant;
    protected array $auth;
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('mifos.enabled', false);
        $this->baseUrl = rtrim(config('mifos.base_url', ''), '/');
        $this->tenant = config('mifos.tenant', 'default');
        $this->auth = [
            'username' => config('mifos.auth.username'),
            'password' => config('mifos.auth.password'),
        ];
    }

    // ─── LoanProviderInterface ────────────────────────────────────────

    public function createLoan(Loan $loan): array
    {
        if (! $this->enabled) {
            return $this->skipped('createLoan');
        }

        $payload = $this->buildLoanPayload($loan);

        return $this->request('post', '/fineract-provider/api/v1/loans', $payload);
    }

    public function updateLoan(Loan $loan): array
    {
        if (! $this->enabled || ! $loan->external_loan_id) {
            return $this->skipped('updateLoan');
        }

        $payload = [
            'approvedOnDate' => $loan->approved_at?->format('d M Y'),
            'expectedDisbursementDate' => $loan->disbursed_at?->format('d M Y'),
            'dateFormat' => 'dd MMMM yyyy',
            'locale' => 'en',
        ];

        return $this->request(
            'put',
            "/fineract-provider/api/v1/loans/{$loan->external_loan_id}",
            $payload
        );
    }

    public function getLoanStatus(string $externalLoanId): array
    {
        if (! $this->enabled) {
            return $this->skipped('getLoanStatus');
        }

        return $this->request(
            'get',
            "/fineract-provider/api/v1/loans/{$externalLoanId}"
        );
    }

    public function approveLoan(Loan $loan): array
    {
        if (! $this->enabled || ! $loan->external_loan_id) {
            return $this->skipped('approveLoan');
        }

        $payload = [
            'approvedOnDate' => now()->format('d M Y'),
            'dateFormat' => 'dd MMMM yyyy',
            'locale' => 'en',
        ];

        return $this->request(
            'post',
            "/fineract-provider/api/v1/loans/{$loan->external_loan_id}?command=approve",
            $payload
        );
    }

    public function rejectLoan(Loan $loan, string $reason): array
    {
        if (! $this->enabled || ! $loan->external_loan_id) {
            return $this->skipped('rejectLoan');
        }

        $payload = [
            'rejectedOnDate' => now()->format('d M Y'),
            'note' => $reason,
            'dateFormat' => 'dd MMMM yyyy',
            'locale' => 'en',
        ];

        return $this->request(
            'post',
            "/fineract-provider/api/v1/loans/{$loan->external_loan_id}?command=reject",
            $payload
        );
    }

    public function disburseLoan(Loan $loan): array
    {
        if (! $this->enabled || ! $loan->external_loan_id) {
            return $this->skipped('disburseLoan');
        }

        $payload = [
            'actualDisbursementDate' => now()->format('d M Y'),
            'dateFormat' => 'dd MMMM yyyy',
            'locale' => 'en',
            'transactionAmount' => $loan->approved_amount,
        ];

        return $this->request(
            'post',
            "/fineract-provider/api/v1/loans/{$loan->external_loan_id}?command=disburse",
            $payload
        );
    }

    public function recordRepayment(Loan $loan, float $amount, array $metadata = []): array
    {
        if (! $this->enabled || ! $loan->external_loan_id) {
            return $this->skipped('recordRepayment');
        }

        $payload = [
            'transactionDate' => now()->format('d M Y'),
            'transactionAmount' => $amount,
            'paymentTypeId' => $metadata['payment_type_id'] ?? 1,
            'dateFormat' => 'dd MMMM yyyy',
            'locale' => 'en',
        ];

        return $this->request(
            'post',
            "/fineract-provider/api/v1/loans/{$loan->external_loan_id}/transactions?command=repayment",
            $payload
        );
    }

    public function getProviderName(): string
    {
        return 'mifos';
    }

    public function isHealthy(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->auth['username'], $this->auth['password'])
                ->timeout(10)
                ->get("{$this->baseUrl}/fineract-provider/api/v1/self/authentication");

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning("MifosAdapter health check failed: {$e->getMessage()}");
            return false;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    protected function buildLoanPayload(Loan $loan): array
    {
        $productId = config('mifos.product_id', 1);
        $officeId = config('mifos.office_id', 1);

        return [
            'clientId' => $this->resolveClientId($loan),
            'productId' => $productId,
            'principal' => (float) ($loan->approved_amount ?? $loan->requested_amount),
            'loanTermFrequency' => $loan->loan_term_days ?? 30,
            'loanTermFrequencyType' => 2, // days
            'numberOfRepayments' => 1,
            'repaymentEvery' => $loan->loan_term_days ?? 30,
            'repaymentFrequencyType' => 2, // days
            'interestRatePerPeriod' => (float) $loan->interest_rate,
            'interestType' => 0, // declining balance
            'interestCalculationPeriodType' => 1, // same as repayment
            'amortizationType' => 1, // equal installments
            'expectedDisbursementDate' => $loan->disbursed_at?->format('d M Y') ?? now()->format('d M Y'),
            'dateFormat' => 'dd MMMM yyyy',
            'locale' => 'en',
            'submittedOnDate' => $loan->submitted_at?->format('d M Y') ?? now()->format('d M Y'),
            'externalId' => $loan->reference,
        ];
    }

    protected function resolveClientId(Loan $loan): ?int
    {
        $borrower = $loan->borrower;

        // If borrower has external client ID stored, use it
        if ($borrower->external_client_id ?? false) {
            return (int) $borrower->external_client_id;
        }

        // Default: create client in Mifos first (out of scope for now)
        // Return null so the caller knows it needs a client
        return null;
    }

    protected function request(string $method, string $endpoint, ?array $payload = null): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $http = Http::withBasicAuth($this->auth['username'], $this->auth['password'])
                ->withHeaders([
                    'Fineract-Platform-TenantId' => $this->tenant,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30);

            $response = match ($method) {
                'get' => $http->get($url),
                'post' => $http->post($url, $payload ?? []),
                'put' => $http->put($url, $payload ?? []),
                'delete' => $http->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            $body = $response->json() ?? [];

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $body,
                ];
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $body['defaultUserMessage'] ?? $body['errors'][0]['defaultUserMessage'] ?? 'Mifos API error',
                'data' => $body,
            ];
        } catch (ConnectionException $e) {
            Log::error("MifosAdapter connection error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'status' => 0,
                'error' => 'Connection error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::error("MifosAdapter unexpected error: {$e->getMessage()}", [
                'endpoint' => $endpoint,
            ]);

            return [
                'success' => false,
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function skipped(string $operation): array
    {
        return [
            'success' => true,
            'skipped' => true,
            'message' => "Mifos integration disabled — {$operation} skipped.",
        ];
    }
}
