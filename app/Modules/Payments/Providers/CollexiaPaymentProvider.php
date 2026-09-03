<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\DTOs\WebhookResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CollexiaPaymentProvider implements PaymentProviderInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultConfig(), $config);
    }

    protected function defaultConfig(): array
    {
        return [
            'base_url' => null,
            'api_key' => null,
            'client_code' => null,
            'sandbox' => true,
            'timeout' => 30,
            'connection_timeout' => 5,
            'webhook_secret' => null,
            'signature_header' => 'X-Webhook-Signature',
            'signature_algorithm' => 'hmac-sha256',
            'health_endpoint' => null,
            'endpoints' => [
                'disbursement' => '/api/v1/payments',
                'lender_return' => '/api/v1/payments',
                'repayment' => '/api/v1/collections',
                'status_check' => '/api/v1/transactions/{reference}',
            ],
            'supported_methods' => [
                PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT => [PaymentInstruction::METHOD_BANK_PAYOUT],
                PaymentInstruction::OPERATION_BORROWER_REPAYMENT => [PaymentInstruction::METHOD_DEBIT_ORDER],
                PaymentInstruction::OPERATION_LENDER_RETURN => [PaymentInstruction::METHOD_BANK_PAYOUT],
            ],
        ];
    }

    public function getName(): string
    {
        return 'collexia';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['base_url']) && ! empty($this->config['api_key']);
    }

    public function isHealthy(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $healthEndpoint = $this->config['health_endpoint'];

        if (! $healthEndpoint) {
            return true;
        }

        try {
            $response = $this->httpClient()
                ->timeout($this->config['connection_timeout'])
                ->get(rtrim($this->config['base_url'], '/').'/'.ltrim($healthEndpoint, '/'));

            return $response->successful();
        } catch (ConnectionException $e) {
            Log::warning('Collexia health check connection failed', [
                'base_url' => $this->config['base_url'],
                'message' => $e->getMessage(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Collexia health check failed', [
                'base_url' => $this->config['base_url'],
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function supports(string $operation, string $paymentMethod): bool
    {
        if ($paymentMethod === PaymentInstruction::METHOD_MANUAL) {
            return true;
        }

        $supported = $this->config['supported_methods'][$operation] ?? [];

        return in_array($paymentMethod, $supported, true);
    }

    public function initiateFunding(PaymentInstruction $instruction): PaymentResult
    {
        return $this->unsupportedResult($instruction, 'Lender funding is not supported by Collexia.');
    }

    public function initiateDisbursement(PaymentInstruction $instruction): PaymentResult
    {
        if (! $this->supports($instruction->operation, $instruction->paymentMethod)) {
            return $this->unsupportedResult($instruction, 'Collexia does not support this disbursement method.');
        }

        $endpoint = $this->config['endpoints']['disbursement'];

        $payload = [
            'reference' => $instruction->reference,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'description' => $instruction->description,
            'destination_account' => $instruction->destinationAccount,
        ];

        return $this->postTransaction($endpoint, $payload, $instruction);
    }

    public function initiateRepayment(PaymentInstruction $instruction): PaymentResult
    {
        if (! $this->supports($instruction->operation, $instruction->paymentMethod)) {
            return $this->unsupportedResult($instruction, 'Collexia does not support this repayment collection method.');
        }

        $endpoint = $this->config['endpoints']['repayment'];

        $payload = [
            'reference' => $instruction->reference,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'description' => $instruction->description,
            'source_account' => $instruction->sourceAccount,
        ];

        return $this->postTransaction($endpoint, $payload, $instruction);
    }

    public function initiateLenderReturn(PaymentInstruction $instruction): PaymentResult
    {
        if (! $this->supports($instruction->operation, $instruction->paymentMethod)) {
            return $this->unsupportedResult($instruction, 'Collexia does not support this lender return method.');
        }

        $endpoint = $this->config['endpoints']['lender_return'];

        $payload = [
            'reference' => $instruction->reference,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'description' => $instruction->description,
            'destination_account' => $instruction->destinationAccount,
        ];

        return $this->postTransaction($endpoint, $payload, $instruction);
    }

    public function checkStatus(string $providerReference): PaymentResult
    {
        $endpoint = str_replace('{reference}', urlencode($providerReference), $this->config['endpoints']['status_check']);

        try {
            $response = $this->httpClient()->get(rtrim($this->config['base_url'], '/').'/'.ltrim($endpoint, '/'));

            if ($response->successful()) {
                return $this->parseApiResponse($response->json() ?? [], $providerReference);
            }

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'Collexia status check returned a non-successful response.',
                rawResponse: $response->json(),
            );
        } catch (ConnectionException $e) {
            return $this->timeoutResult($providerReference);
        } catch (\Throwable $e) {
            Log::warning('Collexia status check failed', [
                'provider_reference' => $providerReference,
                'message' => $e->getMessage(),
            ]);

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'Collexia status check failed: '.$e->getMessage(),
            );
        }
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        $eventType = $payload['event_type'] ?? $payload['type'] ?? 'unknown';
        $reference = $payload['reference'] ?? $payload['merchant_reference'] ?? $payload['external_reference'] ?? null;
        $providerReference = $payload['transaction_id'] ?? $payload['provider_reference'] ?? $payload['reference'] ?? null;
        $providerEventId = $payload['event_id'] ?? $payload['provider_event_id'] ?? null;
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        $currency = $payload['currency'] ?? null;
        $status = $payload['status'] ?? $payload['transaction_status'] ?? null;

        if (! $providerReference && ! $reference) {
            return WebhookResult::notHandled(message: 'Collexia webhook missing transaction reference.');
        }

        return WebhookResult::handled(
            eventType: (string) $eventType,
            reference: $reference,
            providerReference: $providerReference,
            providerEventId: $providerEventId,
            status: $this->mapWebhookStatus($status),
            amount: $amount,
            currency: $currency,
            message: "Collexia webhook event [{$eventType}] received with status [{$status}].",
            metadata: ['raw_payload' => $payload],
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->config['webhook_secret'])) {
            return true;
        }

        $signature = $request->header($this->config['signature_header']);

        if (! $signature) {
            return false;
        }

        $algorithm = strtolower($this->config['signature_algorithm']);

        if ($algorithm === 'hmac-sha256' || $algorithm === 'sha256') {
            $expected = hash_hmac('sha256', $request->getContent(), $this->config['webhook_secret']);

            return hash_equals($expected, $signature);
        }

        return false;
    }

    protected function postTransaction(string $endpoint, array $payload, PaymentInstruction $instruction): PaymentResult
    {
        if (! $this->isConfigured()) {
            return $this->configurationError($instruction);
        }

        try {
            $response = $this->httpClient()
                ->post(rtrim($this->config['base_url'], '/').'/'.ltrim($endpoint, '/'), $payload);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                return $this->parseApiResponse($data, $instruction->reference, $instruction);
            }

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                externalReference: $instruction->reference,
                message: $data['message'] ?? 'Collexia API returned an error response.',
                rawResponse: $data,
            );
        } catch (ConnectionException $e) {
            return $this->timeoutResult($instruction->reference);
        } catch (\Throwable $e) {
            Log::warning('Collexia transaction request failed', [
                'operation' => $instruction->operation,
                'reference' => $instruction->reference,
                'message' => $e->getMessage(),
            ]);

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                externalReference: $instruction->reference,
                message: 'Collexia transaction request failed: '.$e->getMessage(),
            );
        }
    }

    protected function parseApiResponse(array $data, ?string $reference = null, ?PaymentInstruction $instruction = null): PaymentResult
    {
        $providerReference = $data['transaction_id'] ?? $data['reference'] ?? $data['id'] ?? null;
        $status = strtolower($data['status'] ?? 'unknown');
        $message = $data['message'] ?? 'Collexia response received.';

        $mappedStatus = $this->mapStatus($status);
        $success = in_array($mappedStatus, [PaymentResult::STATUS_COMPLETED, PaymentResult::STATUS_PENDING], true);

        return PaymentResult::make(
            success: $success,
            status: $mappedStatus,
            providerName: $this->getName(),
            providerReference: $providerReference,
            externalReference: $reference,
            message: $message,
            metadata: [
                'operation' => $instruction?->operation,
                'payment_method' => $instruction?->paymentMethod,
                'amount' => $instruction?->amount ?? ($data['amount'] ?? null),
                'currency' => $instruction?->currency ?? ($data['currency'] ?? null),
            ],
            rawResponse: $data,
        );
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'completed', 'successful', 'success', 'settled' => PaymentResult::STATUS_COMPLETED,
            'pending', 'processing', 'submitted' => PaymentResult::STATUS_PENDING,
            'failed', 'rejected', 'error', 'declined' => PaymentResult::STATUS_FAILED,
            'reversed', 'refunded', 'chargeback' => PaymentResult::STATUS_REVERSED,
            'timeout' => PaymentResult::STATUS_TIMEOUT,
            default => PaymentResult::STATUS_PENDING,
        };
    }

    protected function mapWebhookStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        return $this->mapStatus(strtolower($status));
    }

    protected function httpClient(): PendingRequest
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (! empty($this->config['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$this->config['api_key'];
        }

        if (! empty($this->config['client_code'])) {
            $headers['X-Client-Code'] = $this->config['client_code'];
        }

        return Http::withHeaders($headers)
            ->timeout($this->config['timeout']);
    }

    protected function unsupportedResult(PaymentInstruction $instruction, string $message): PaymentResult
    {
        return PaymentResult::make(
            success: false,
            status: PaymentResult::STATUS_UNSUPPORTED,
            providerName: $this->getName(),
            externalReference: $instruction->reference,
            message: $message,
            metadata: [
                'operation' => $instruction->operation,
                'payment_method' => $instruction->paymentMethod,
            ],
        );
    }

    protected function configurationError(PaymentInstruction $instruction): PaymentResult
    {
        return PaymentResult::make(
            success: false,
            status: PaymentResult::STATUS_FAILED,
            providerName: $this->getName(),
            externalReference: $instruction->reference,
            message: 'Collexia is not configured. Missing base URL or API key.',
        );
    }

    protected function timeoutResult(string $reference): PaymentResult
    {
        return PaymentResult::make(
            success: false,
            status: PaymentResult::STATUS_TIMEOUT,
            providerName: $this->getName(),
            externalReference: $reference,
            message: 'Collexia request timed out or could not establish a connection.',
        );
    }
}
