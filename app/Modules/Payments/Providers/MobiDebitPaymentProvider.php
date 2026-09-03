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

class MobiDebitPaymentProvider implements PaymentProviderInterface
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
            'sandbox' => true,
            'timeout' => 30,
            'connection_timeout' => 5,
            'redirect_url' => null,
            'response_url' => null,
            'webhook_secret' => null,
            'signature_header' => 'X-Webhook-Signature',
            'signature_algorithm' => 'hmac-sha256',
            'health_endpoint' => null,
            'endpoints' => [
                'payment_request' => '/v2/payment-requests/',
                'status_check' => '/v2/payment-requests/{reference}',
            ],
            'supported_methods' => [
                PaymentInstruction::OPERATION_LENDER_FUNDING => [PaymentInstruction::METHOD_PAYMENT_LINK],
                PaymentInstruction::OPERATION_BORROWER_REPAYMENT => [
                    PaymentInstruction::METHOD_PAYMENT_LINK,
                    PaymentInstruction::METHOD_DEBIT_ORDER,
                ],
            ],
        ];
    }

    public function getName(): string
    {
        return 'mobidebit';
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
            Log::warning('MobiDebit health check connection failed', [
                'base_url' => $this->config['base_url'],
                'message' => $e->getMessage(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('MobiDebit health check failed', [
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
        if (! $this->supports($instruction->operation, $instruction->paymentMethod)) {
            return $this->unsupportedResult($instruction, 'MobiDebit does not support this lender funding method.');
        }

        return $this->createPaymentRequest($instruction, [
            'request_methods' => ['WEB'],
            'fixed_amount' => true,
        ]);
    }

    public function initiateDisbursement(PaymentInstruction $instruction): PaymentResult
    {
        return $this->unsupportedResult($instruction, 'MobiDebit does not support outbound bank payouts.');
    }

    public function initiateRepayment(PaymentInstruction $instruction): PaymentResult
    {
        if (! $this->supports($instruction->operation, $instruction->paymentMethod)) {
            return $this->unsupportedResult($instruction, 'MobiDebit does not support this repayment collection method.');
        }

        $overrides = [
            'request_methods' => ['WEB'],
            'fixed_amount' => true,
        ];

        if ($instruction->paymentMethod === PaymentInstruction::METHOD_DEBIT_ORDER) {
            $overrides['payment_type'] = 'DB';
        }

        return $this->createPaymentRequest($instruction, $overrides);
    }

    public function initiateLenderReturn(PaymentInstruction $instruction): PaymentResult
    {
        return $this->unsupportedResult($instruction, 'MobiDebit does not support outbound lender returns.');
    }

    public function checkStatus(string $providerReference): PaymentResult
    {
        $endpoint = str_replace('{reference}', urlencode($providerReference), $this->config['endpoints']['status_check']);

        try {
            $response = $this->httpClient()->get(rtrim($this->config['base_url'], '/').'/'.ltrim($endpoint, '/'));

            if ($response->successful()) {
                $data = $response->json() ?? [];

                // Status endpoint can return either a single transaction object or a paginated list.
                $transaction = $data['transactions'][0] ?? $data;

                return $this->parseApiResponse($transaction, $providerReference);
            }

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'MobiDebit status check returned a non-successful response.',
                rawResponse: $response->json(),
            );
        } catch (ConnectionException $e) {
            return $this->timeoutResult($providerReference);
        } catch (\Throwable $e) {
            Log::warning('MobiDebit status check failed', [
                'provider_reference' => $providerReference,
                'message' => $e->getMessage(),
            ]);

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'MobiDebit status check failed: '.$e->getMessage(),
            );
        }
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        // Mobipaid callbacks POST a parameter named "response" that contains a JSON string.
        $raw = $payload['response'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $result = $payload['result'] ?? null;
        $resultCode = $payload['result_code'] ?? null;
        $providerReference = $payload['transaction_id'] ?? $payload['payment_id'] ?? null;
        $reference = $payload['reference_number'] ?? $payload['merchant_reference'] ?? null;
        $providerEventId = $payload['payment_id'] ?? $providerReference;
        $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
        $currency = $payload['currency'] ?? null;
        $eventType = 'payment.response';

        if (! $providerReference && ! $reference) {
            return WebhookResult::notHandled(message: 'MobiDebit callback missing transaction reference.');
        }

        $status = $this->mapCallbackStatus($result, $resultCode);

        return WebhookResult::handled(
            eventType: $eventType,
            reference: $reference,
            providerReference: $providerReference,
            providerEventId: $providerEventId,
            status: $status,
            amount: $amount,
            currency: $currency,
            message: "MobiDebit callback received with result [{$result}] and code [{$resultCode}].",
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

    protected function createPaymentRequest(PaymentInstruction $instruction, array $overrides): PaymentResult
    {
        if (! $this->isConfigured()) {
            return $this->configurationError($instruction);
        }

        $endpoint = $this->config['endpoints']['payment_request'];

        $payload = [
            'reference_number' => $instruction->reference,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'fixed_amount' => true,
            'redirect_url' => $this->config['redirect_url'] ?? route('home'),
            'response_url' => $this->config['response_url'] ?? route('home'),
        ];

        if ($instruction->description) {
            $payload['description'] = $instruction->description;
        }

        if (! empty($instruction->metadata['email'])) {
            $payload['email'] = $instruction->metadata['email'];
            $payload['request_methods'] = ['EMAIL'];
        } elseif (! empty($instruction->metadata['mobile_number'])) {
            $payload['mobile_number'] = $instruction->metadata['mobile_number'];
            $payload['request_methods'] = ['SMS'];
        } else {
            $payload['request_methods'] = ['WEB'];
        }

        $payload = array_merge($payload, $overrides);

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
                message: $data['message'] ?? $data['error_message'] ?? 'MobiDebit API returned an error response.',
                rawResponse: $data,
            );
        } catch (ConnectionException $e) {
            return $this->timeoutResult($instruction->reference);
        } catch (\Throwable $e) {
            Log::warning('MobiDebit payment request failed', [
                'operation' => $instruction->operation,
                'reference' => $instruction->reference,
                'message' => $e->getMessage(),
            ]);

            return PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                externalReference: $instruction->reference,
                message: 'MobiDebit payment request failed: '.$e->getMessage(),
            );
        }
    }

    protected function parseApiResponse(array $data, ?string $reference = null, ?PaymentInstruction $instruction = null): PaymentResult
    {
        $providerReference = $data['transaction_id'] ?? $data['payment_id'] ?? $data['id'] ?? null;
        $status = strtolower($data['status'] ?? $data['result'] ?? 'unknown');
        $message = $data['result_description'] ?? $data['message'] ?? 'MobiDebit response received.';

        $mappedStatus = $this->mapStatus($status);
        $success = in_array($mappedStatus, [PaymentResult::STATUS_COMPLETED, PaymentResult::STATUS_PENDING], true);

        $metadata = [
            'operation' => $instruction?->operation,
            'payment_method' => $instruction?->paymentMethod,
            'payment_url' => $data['short_url'] ?? $data['long_url'] ?? null,
            'qrcode_link' => $data['qrcode_link'] ?? null,
        ];

        if (! $instruction) {
            unset($metadata['operation'], $metadata['payment_method']);
        }

        return PaymentResult::make(
            success: $success,
            status: $mappedStatus,
            providerName: $this->getName(),
            providerReference: $providerReference,
            externalReference: $reference,
            message: $message,
            metadata: array_merge($metadata, [
                'amount' => $instruction?->amount ?? ($data['amount'] ?? null),
                'currency' => $instruction?->currency ?? ($data['currency'] ?? null),
            ]),
            rawResponse: $data,
        );
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'success', 'completed', 'successful', 'ack' => PaymentResult::STATUS_COMPLETED,
            'pending', 'processing', 'initialized' => PaymentResult::STATUS_PENDING,
            'failed', 'rejected', 'error', 'declined', 'nok' => PaymentResult::STATUS_FAILED,
            'reversed', 'refunded', 'chargeback' => PaymentResult::STATUS_REVERSED,
            'timeout' => PaymentResult::STATUS_TIMEOUT,
            default => PaymentResult::STATUS_PENDING,
        };
    }

    protected function mapCallbackStatus(?string $result, ?string $resultCode): ?string
    {
        if ($result === 'ACK') {
            if ($resultCode && str_starts_with($resultCode, '000.200')) {
                return PaymentResult::STATUS_PENDING;
            }

            if ($resultCode && str_starts_with($resultCode, '000.4')) {
                return PaymentResult::STATUS_PENDING;
            }

            return PaymentResult::STATUS_COMPLETED;
        }

        if ($result === 'NOK') {
            return PaymentResult::STATUS_FAILED;
        }

        if ($resultCode) {
            if (str_starts_with($resultCode, '000.200')) {
                return PaymentResult::STATUS_PENDING;
            }

            if (str_starts_with($resultCode, '000.4')) {
                return PaymentResult::STATUS_PENDING;
            }

            if (str_starts_with($resultCode, '000.000') || str_starts_with($resultCode, '000.100') || str_starts_with($resultCode, '000.300') || str_starts_with($resultCode, '000.600')) {
                return PaymentResult::STATUS_COMPLETED;
            }
        }

        return null;
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
            message: 'MobiDebit is not configured. Missing base URL or API key.',
        );
    }

    protected function timeoutResult(string $reference): PaymentResult
    {
        return PaymentResult::make(
            success: false,
            status: PaymentResult::STATUS_TIMEOUT,
            providerName: $this->getName(),
            externalReference: $reference,
            message: 'MobiDebit request timed out or could not establish a connection.',
        );
    }
}
