<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\Providers\ManualPaymentProvider;
use App\Modules\Payments\Providers\MobiDebitPaymentProvider;
use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobiDebitPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->configureMobiDebit();
    }

    protected function configureMobiDebit(): void
    {
        config([
            'payment_providers.default_provider' => 'mobidebit',
            'payment_providers.providers.mobidebit' => [
                'driver' => 'mobidebit',
                'base_url' => 'https://test.mobipaid.io',
                'api_key' => 'mp_test_w3Lwv3CZIZzye4cYxxr0',
                'sandbox' => true,
                'timeout' => 30,
                'connection_timeout' => 5,
                'redirect_url' => 'https://quickshare.example.com/payment/receipt',
                'response_url' => 'https://quickshare.example.com/api/payments/webhooks/mobidebit',
                'webhook_secret' => 'test-webhook-secret',
                'signature_header' => 'X-Webhook-Signature',
                'signature_algorithm' => 'hmac-sha256',
                'health_endpoint' => null,
                'endpoints' => [
                    'payment_request' => '/v2/payment-requests/',
                    'status_check' => '/v2/payment-requests/{reference}',
                ],
                'supported_methods' => [
                    'lender_funding' => ['payment_link'],
                    'borrower_repayment' => ['payment_link', 'debit_order'],
                ],
            ],
        ]);
    }

    protected function provider(): MobiDebitPaymentProvider
    {
        return new MobiDebitPaymentProvider(config('payment_providers.providers.mobidebit'));
    }

    protected function manager(): PaymentProviderManager
    {
        return app(PaymentProviderManager::class);
    }

    protected function instruction(
        string $operation,
        string $paymentMethod,
        float $amount = 1000.00,
        string $reference = 'TEST-REF-001',
        array $metadata = [],
    ): PaymentInstruction {
        return new PaymentInstruction(
            operation: $operation,
            paymentMethod: $paymentMethod,
            executionMode: PaymentInstruction::EXECUTION_AUTOMATED,
            amount: $amount,
            reference: $reference,
            currency: 'NAD',
            metadata: $metadata,
        );
    }

    protected function fakeMobiDebitApi(array $responses): void
    {
        Http::fake(function ($request) use ($responses) {
            $url = $request->url();
            $method = $request->method();
            $key = $method.' '.$url;

            foreach ($responses as $pattern => $response) {
                if ($key === $pattern || $url === $pattern) {
                    return $response;
                }
            }

            return Http::response(['status' => 'unknown', 'message' => 'Unfaked request'], 200);
        });
    }

    // ─── Provider Lifecycle ────────────────────────────────────────────

    public function test_mobidebit_provider_is_registered_and_implements_interface(): void
    {
        $provider = $this->manager()->resolve('mobidebit');

        $this->assertInstanceOf(MobiDebitPaymentProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertSame('mobidebit', $provider->getName());
    }

    public function test_provider_reports_configured_when_credentials_present(): void
    {
        $this->assertTrue($this->provider()->isConfigured());
        $this->assertTrue($this->provider()->isHealthy());
    }

    public function test_provider_reports_not_configured_when_missing_credentials(): void
    {
        $provider = new MobiDebitPaymentProvider(['base_url' => null, 'api_key' => null]);

        $this->assertFalse($provider->isConfigured());
        $this->assertFalse($provider->isHealthy());
    }

    // ─── Capability Matrix ─────────────────────────────────────────────

    public function test_supports_confirmed_capabilities(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_MANUAL));
    }

    public function test_does_not_support_unconfirmed_or_wrong_direction_methods(): void
    {
        $provider = $this->provider();

        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_WALLET_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_PAYMENT_LINK));
    }

    // ─── Unsupported Outbound Operations ───────────────────────────────

    public function test_borrower_disbursement_is_not_supported(): void
    {
        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_UNSUPPORTED, $result->status);
        $this->assertSame('mobidebit', $result->providerName);
    }

    public function test_lender_return_is_not_supported(): void
    {
        $result = $this->provider()->initiateLenderReturn($this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_UNSUPPORTED, $result->status);
        $this->assertSame('mobidebit', $result->providerName);
    }

    // ─── Lender Funding via Payment Link ─────────────────────────────────

    public function test_successful_lender_funding_returns_completed(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'transaction_id' => 'MBD-FUND-123',
                'reference_number' => 'TEST-REF-001',
                'result' => 'success',
                'status' => 'success',
                'amount' => '1000.00',
                'currency' => 'NAD',
                'short_url' => 'https://pay.test/MBD-FUND-123',
                'long_url' => 'https://pay.test/long/MBD-FUND-123',
                'qrcode_link' => 'https://pay.test/qr/MBD-FUND-123',
            ], 200),
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-FUND-123', $result->providerReference);
        $this->assertSame('https://pay.test/MBD-FUND-123', $result->metadata['payment_url']);
        $this->assertSame('TEST-REF-001', $result->externalReference);
    }

    public function test_pending_lender_funding_returns_pending(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'transaction_id' => 'MBD-FUND-456',
                'reference_number' => 'TEST-REF-001',
                'status' => 'pending',
                'short_url' => 'https://pay.test/MBD-FUND-456',
            ], 200),
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
        $this->assertSame('MBD-FUND-456', $result->providerReference);
    }

    public function test_failed_lender_funding_returns_failed(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'is_valid' => false,
                'error_field' => 'amount',
                'error_message' => 'Invalid amount',
                'result' => 'failed',
            ], 400),
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_lender_funding_timeout_returns_timeout(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Borrower Repayment via Payment Link ────────────────────────────

    public function test_successful_repayment_payment_link_returns_completed(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'transaction_id' => 'MBD-REP-123',
                'reference_number' => 'TEST-REF-001',
                'result' => 'success',
                'status' => 'success',
                'amount' => '1000.00',
                'currency' => 'NAD',
                'short_url' => 'https://pay.test/MBD-REP-123',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-REP-123', $result->providerReference);
    }

    public function test_pending_repayment_payment_link_returns_pending(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'transaction_id' => 'MBD-REP-456',
                'reference_number' => 'TEST-REF-001',
                'status' => 'pending',
                'short_url' => 'https://pay.test/MBD-REP-456',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_failed_repayment_payment_link_returns_failed(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'is_valid' => false,
                'error_message' => 'Currency not supported',
                'result' => 'failed',
            ], 402),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_repayment_payment_link_timeout_returns_timeout(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Borrower Repayment via Debit Order ─────────────────────────────

    public function test_successful_repayment_debit_order_returns_completed(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'transaction_id' => 'MBD-DEBIT-123',
                'reference_number' => 'TEST-REF-001',
                'result' => 'success',
                'status' => 'success',
                'payment_type' => 'DB',
                'amount' => '1000.00',
                'currency' => 'NAD',
                'short_url' => 'https://pay.test/MBD-DEBIT-123',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-DEBIT-123', $result->providerReference);
    }

    public function test_pending_repayment_debit_order_returns_pending(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'transaction_id' => 'MBD-DEBIT-456',
                'reference_number' => 'TEST-REF-001',
                'status' => 'pending',
                'payment_type' => 'DB',
                'short_url' => 'https://pay.test/MBD-DEBIT-456',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_failed_repayment_debit_order_returns_failed(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => Http::response([
                'is_valid' => false,
                'error_message' => 'Direct debit not enabled',
                'result' => 'failed',
            ], 403),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    // ─── Status Check ──────────────────────────────────────────────────

    public function test_status_check_maps_completed_status(): void
    {
        $this->fakeMobiDebitApi([
            'GET https://test.mobipaid.io/v2/payment-requests/MBD-STATUS-1' => Http::response([
                'status' => 'success',
                'transaction_id' => 'MBD-STATUS-1',
                'amount' => 1000.00,
                'currency' => 'NAD',
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('MBD-STATUS-1');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
    }

    public function test_status_check_maps_pending_status(): void
    {
        $this->fakeMobiDebitApi([
            'GET https://test.mobipaid.io/v2/payment-requests/MBD-STATUS-2' => Http::response([
                'status' => 'pending',
                'transaction_id' => 'MBD-STATUS-2',
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('MBD-STATUS-2');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_status_check_failure_returns_failed(): void
    {
        $this->fakeMobiDebitApi([
            'GET https://test.mobipaid.io/v2/payment-requests/MBD-STATUS-3' => Http::response([
                'status' => 'failed',
                'transaction_id' => 'MBD-STATUS-3',
            ], 422),
        ]);

        $result = $this->provider()->checkStatus('MBD-STATUS-3');

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_status_check_timeout_returns_timeout(): void
    {
        $this->fakeMobiDebitApi([
            'GET https://test.mobipaid.io/v2/payment-requests/MBD-STATUS-4' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->checkStatus('MBD-STATUS-4');

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    public function test_status_check_handles_paginated_list_response(): void
    {
        $this->fakeMobiDebitApi([
            'GET https://test.mobipaid.io/v2/payment-requests/MBD-STATUS-5' => Http::response([
                'transactions' => [
                    [
                        'status' => 'success',
                        'transaction_id' => 'MBD-STATUS-5',
                        'amount' => 1000.00,
                    ],
                ],
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('MBD-STATUS-5');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
    }

    // ─── Webhook Parsing ────────────────────────────────────────────────

    public function test_webhook_maps_ack_completed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'transaction_id' => 'MBD-WEB-1',
                'reference_number' => 'TEST-REF-WEB-1',
                'amount' => '1000.00',
                'currency' => 'NAD',
            ]),
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('payment.response', $result->eventType);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-WEB-1', $result->providerReference);
        $this->assertSame('TEST-REF-WEB-1', $result->reference);
        $this->assertSame(1000.00, $result->amount);
    }

    public function test_webhook_maps_ack_pending_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.200.000',
                'transaction_id' => 'MBD-WEB-2',
                'reference_number' => 'TEST-REF-WEB-2',
                'amount' => '1000.00',
            ]),
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_webhook_maps_ack_review_pending_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.400.000',
                'transaction_id' => 'MBD-WEB-2B',
                'reference_number' => 'TEST-REF-WEB-2B',
                'amount' => '1000.00',
            ]),
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_webhook_maps_nok_failed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'response' => json_encode([
                'result' => 'NOK',
                'result_code' => '100.400.000',
                'transaction_id' => 'MBD-WEB-3',
                'reference_number' => 'TEST-REF-WEB-3',
                'amount' => '1000.00',
            ]),
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_webhook_parses_flat_payload_when_no_response_wrapper(): void
    {
        $result = $this->provider()->handleWebhook([
            'result' => 'ACK',
            'result_code' => '000.000.000',
            'transaction_id' => 'MBD-WEB-4',
            'reference_number' => 'TEST-REF-WEB-4',
            'amount' => '500.00',
            'currency' => 'NAD',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame(500.00, $result->amount);
    }

    public function test_webhook_without_reference_is_not_handled(): void
    {
        $result = $this->provider()->handleWebhook([
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'amount' => '1000.00',
            ]),
        ]);

        $this->assertFalse($result->success);
        $this->assertNull($result->providerReference);
    }

    // ─── Webhook Signature Verification ────────────────────────────────

    public function test_valid_webhook_signature_is_verified(): void
    {
        $payload = json_encode([
            'response' => json_encode([
                'result' => 'ACK',
                'transaction_id' => 'MBD-SIG-1',
                'amount' => '1000.00',
            ]),
        ]);

        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $request = Request::create(
            '/api/payments/webhooks/mobidebit',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Webhook-Signature', $signature);

        $this->assertTrue($this->provider()->verifyWebhookSignature($request));
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $payload = json_encode([
            'response' => json_encode([
                'result' => 'ACK',
                'transaction_id' => 'MBD-SIG-2',
            ]),
        ]);

        $request = Request::create(
            '/api/payments/webhooks/mobidebit',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );
        $request->headers->set('X-Webhook-Signature', 'invalid-signature');

        $this->assertFalse($this->provider()->verifyWebhookSignature($request));
    }

    public function test_webhook_signature_without_secret_is_accepted(): void
    {
        $provider = new MobiDebitPaymentProvider(['webhook_secret' => null]);

        $request = Request::create('/api/payments/webhooks/mobidebit', 'POST', []);

        $this->assertTrue($provider->verifyWebhookSignature($request));
    }

    // ─── End-to-End Webhook Controller Scenarios ─────────────────────────

    protected function createFundingTransaction(
        float $amount = 1000.00,
        string $providerReference = 'MBD-FUND-E2E',
    ): FundingTransaction {
        $user = User::factory()->active()->create();
        $loan = Loan::factory()->create();

        return FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $user->id,
            'amount' => $amount,
            'interest_rate' => 15,
            'expected_return' => $amount * 1.15,
            'status' => 'pending',
            'transaction_reference' => 'FUND-'.strtoupper(Str::random(12)),
            'provider_reference' => $providerReference,
        ]);
    }

    protected function createRepayment(
        float $amount = 1000.00,
        string $providerReference = 'MBD-REP-E2E',
    ): Repayment {
        return Repayment::factory()->create([
            'amount' => $amount,
            'status' => 'pending_approval',
            'provider_reference' => $providerReference,
        ]);
    }

    protected function signedCallbackPayload(array $payload): array
    {
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, 'test-webhook-secret');

        return [
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    public function test_successful_callback_updates_funding_transaction(): void
    {
        $transaction = $this->createFundingTransaction();
        $callback = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'transaction_id' => 'MBD-FUND-E2E',
                'reference_number' => $transaction->transaction_reference,
                'amount' => '1000.00',
                'currency' => 'NAD',
            ]),
        ];
        $signed = $this->signedCallbackPayload($callback);

        $response = $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'MBD-FUND-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'funding_transaction',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_pending_callback_is_recorded_for_repayment(): void
    {
        $repayment = $this->createRepayment();
        $callback = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.200.000',
                'transaction_id' => 'MBD-REP-E2E',
                'reference_number' => $repayment->transaction_reference,
                'amount' => '1000.00',
                'currency' => 'NAD',
            ]),
        ];
        $signed = $this->signedCallbackPayload($callback);

        $response = $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'MBD-REP-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'repayment',
            'transaction_id' => $repayment->id,
        ]);
    }

    public function test_failed_callback_is_recorded_for_repayment(): void
    {
        $repayment = $this->createRepayment();
        $callback = [
            'response' => json_encode([
                'result' => 'NOK',
                'result_code' => '100.400.000',
                'transaction_id' => 'MBD-REP-E2E',
                'reference_number' => $repayment->transaction_reference,
                'amount' => '1000.00',
            ]),
        ];
        $signed = $this->signedCallbackPayload($callback);

        $response = $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'MBD-REP-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'repayment',
            'transaction_id' => $repayment->id,
        ]);
    }

    public function test_duplicate_callback_is_not_processed_twice(): void
    {
        $repayment = $this->createRepayment();
        $callback = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'payment_id' => 'MBD-DUP-1',
                'transaction_id' => 'MBD-REP-E2E',
                'reference_number' => $repayment->transaction_reference,
                'amount' => '1000.00',
            ]),
        ];
        $signed = $this->signedCallbackPayload($callback);

        $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ])->assertOk();

        $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ])->assertOk()->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertCount(1, PaymentWebhookEvent::where('provider_event_id', 'MBD-DUP-1')->get());
    }

    public function test_callback_with_invalid_signature_is_rejected(): void
    {
        $repayment = $this->createRepayment();
        $callback = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'transaction_id' => 'MBD-REP-E2E',
                'reference_number' => $repayment->transaction_reference,
                'amount' => '1000.00',
            ]),
        ];

        $response = $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => 'bad-signature',
        ]);

        $response->assertUnauthorized();
    }

    public function test_callback_with_wrong_amount_is_rejected(): void
    {
        $repayment = $this->createRepayment(1000.00);
        $callback = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'transaction_id' => 'MBD-REP-E2E',
                'reference_number' => $repayment->transaction_reference,
                'amount' => '500.00',
            ]),
        ];
        $signed = $this->signedCallbackPayload($callback);

        $response = $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Webhook amount does not match transaction amount.');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'MBD-REP-E2E',
            'status' => PaymentWebhookEvent::STATUS_INVALID,
        ]);
    }

    public function test_callback_with_unknown_reference_is_rejected(): void
    {
        $callback = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'transaction_id' => 'MBD-UNKNOWN',
                'amount' => '1000.00',
            ]),
        ];
        $signed = $this->signedCallbackPayload($callback);

        $response = $this->postJson('/api/payments/webhooks/mobidebit', $callback, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No internal transaction found for provider reference.');
    }

    public function test_timeout_is_handled_by_status_check_and_initiation(): void
    {
        $this->fakeMobiDebitApi([
            'POST https://test.mobipaid.io/v2/payment-requests/' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Manual Fallback ─────────────────────────────────────────────────

    public function test_manual_execution_bypasses_mobidebit_and_preserves_workflow(): void
    {
        config(['payment_providers.default_provider' => 'manual']);

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_LENDER_FUNDING,
            paymentMethod: PaymentInstruction::METHOD_MANUAL,
            executionMode: PaymentInstruction::EXECUTION_MANUAL,
            amount: 1000.00,
            reference: 'MANUAL-REF-001',
        );

        $manager = $this->manager();
        $provider = $manager->forInstruction($instruction);

        $this->assertSame('manual', $provider->getName());

        $result = $provider->initiateFunding($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
    }

    public function test_unconfigured_mobidebit_keeps_manual_instructions_on_manual_path(): void
    {
        config([
            'payment_providers.default_provider' => 'mobidebit',
            'payment_providers.providers.mobidebit.base_url' => null,
            'payment_providers.providers.mobidebit.api_key' => null,
        ]);

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            paymentMethod: PaymentInstruction::METHOD_MANUAL,
            executionMode: PaymentInstruction::EXECUTION_MANUAL,
            amount: 1000.00,
            reference: 'MANUAL-REF-002',
        );

        $manager = $this->manager();
        $provider = $manager->forInstruction($instruction);

        $this->assertSame('manual', $provider->getName());
        $this->assertInstanceOf(ManualPaymentProvider::class, $provider);
    }
}
