<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\Providers\CollexiaPaymentProvider;
use App\Modules\Payments\Providers\ManualPaymentProvider;
use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollexiaPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->configureCollexia();
    }

    protected function configureCollexia(): void
    {
        config([
            'payment_providers.default_provider' => 'collexia',
            'payment_providers.providers.collexia' => [
                'driver' => 'collexia',
                'base_url' => 'https://sandbox-api.collexia.co',
                'api_key' => 'test-api-key',
                'client_code' => 'TESTCLIENT',
                'sandbox' => true,
                'timeout' => 30,
                'connection_timeout' => 5,
                'webhook_secret' => 'test-webhook-secret',
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
                    'borrower_disbursement' => ['bank_payout'],
                    'borrower_repayment' => ['debit_order'],
                    'lender_returns' => ['bank_payout'],
                ],
            ],
        ]);
    }

    protected function provider(): CollexiaPaymentProvider
    {
        return new CollexiaPaymentProvider(config('payment_providers.providers.collexia'));
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
    ): PaymentInstruction {
        return new PaymentInstruction(
            operation: $operation,
            paymentMethod: $paymentMethod,
            executionMode: PaymentInstruction::EXECUTION_AUTOMATED,
            amount: $amount,
            reference: $reference,
            currency: 'NAD',
            loanId: 1,
            userId: 1,
            destinationAccount: [
                'bank_code' => 'FNB',
                'account_number' => '1234567890',
                'account_name' => 'Test Account',
            ],
            sourceAccount: [
                'bank_code' => 'FNB',
                'account_number' => '1234567890',
                'account_name' => 'Test Account',
            ],
        );
    }

    protected function fakeCollexiaApi(array $responses): void
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

    public function test_collexia_provider_is_registered_and_implements_interface(): void
    {
        $provider = $this->manager()->resolve('collexia');

        $this->assertInstanceOf(CollexiaPaymentProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertSame('collexia', $provider->getName());
    }

    public function test_provider_reports_configured_when_credentials_present(): void
    {
        $this->assertTrue($this->provider()->isConfigured());
        $this->assertTrue($this->provider()->isHealthy());
    }

    public function test_provider_reports_not_configured_when_missing_credentials(): void
    {
        $provider = new CollexiaPaymentProvider(['base_url' => null, 'api_key' => null]);

        $this->assertFalse($provider->isConfigured());
        $this->assertFalse($provider->isHealthy());
    }

    // ─── Capability Matrix ─────────────────────────────────────────────

    public function test_supports_confirmed_capabilities(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_MANUAL));
    }

    public function test_does_not_support_unconfirmed_or_wrong_direction_methods(): void
    {
        $provider = $this->provider();

        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_WALLET_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_WALLET_PAYOUT));
    }

    public function test_lender_funding_is_not_supported(): void
    {
        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_UNSUPPORTED, $result->status);
        $this->assertSame('collexia', $result->providerName);
    }

    // ─── Borrower Disbursement ─────────────────────────────────────────

    public function test_successful_disbursement_returns_completed(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/payments' => Http::response([
                'transaction_id' => 'CLX-DISB-123',
                'status' => 'completed',
                'message' => 'Payment queued',
            ], 200),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-DISB-123', $result->providerReference);
        $this->assertSame('TEST-REF-001', $result->externalReference);
    }

    public function test_pending_disbursement_returns_pending(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/payments' => Http::response([
                'transaction_id' => 'CLX-DISB-456',
                'status' => 'pending',
                'message' => 'Payment pending',
            ], 200),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
        $this->assertSame('CLX-DISB-456', $result->providerReference);
    }

    public function test_failed_disbursement_returns_failed(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/payments' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid account',
            ], 422),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_disbursement_timeout_returns_timeout(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/payments' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Borrower Repayment ────────────────────────────────────────────

    public function test_successful_repayment_collection_returns_completed(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/collections' => Http::response([
                'transaction_id' => 'CLX-COL-123',
                'status' => 'successful',
                'message' => 'Collection submitted',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-COL-123', $result->providerReference);
    }

    public function test_pending_repayment_collection_returns_pending(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/collections' => Http::response([
                'transaction_id' => 'CLX-COL-456',
                'status' => 'processing',
                'message' => 'Collection processing',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_failed_repayment_collection_returns_failed(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/collections' => Http::response([
                'status' => 'rejected',
                'message' => 'Insufficient funds',
            ], 422),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_repayment_collection_timeout_returns_timeout(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/collections' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Lender Returns ──────────────────────────────────────────────────

    public function test_successful_lender_return_returns_completed(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/payments' => Http::response([
                'transaction_id' => 'CLX-RET-123',
                'status' => 'completed',
                'message' => 'Payout queued',
            ], 200),
        ]);

        $result = $this->provider()->initiateLenderReturn($this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-RET-123', $result->providerReference);
    }

    // ─── Status Check ──────────────────────────────────────────────────

    public function test_status_check_maps_completed_status(): void
    {
        $this->fakeCollexiaApi([
            'GET https://sandbox-api.collexia.co/api/v1/transactions/CLX-STATUS-1' => Http::response([
                'status' => 'completed',
                'transaction_id' => 'CLX-STATUS-1',
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('CLX-STATUS-1');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
    }

    public function test_status_check_maps_pending_status(): void
    {
        $this->fakeCollexiaApi([
            'GET https://sandbox-api.collexia.co/api/v1/transactions/CLX-STATUS-2' => Http::response([
                'status' => 'submitted',
                'transaction_id' => 'CLX-STATUS-2',
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('CLX-STATUS-2');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_status_check_failure_returns_failed(): void
    {
        $this->fakeCollexiaApi([
            'GET https://sandbox-api.collexia.co/api/v1/transactions/CLX-STATUS-3' => Http::response([
                'status' => 'failed',
            ], 422),
        ]);

        $result = $this->provider()->checkStatus('CLX-STATUS-3');

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_status_check_timeout_returns_timeout(): void
    {
        $this->fakeCollexiaApi([
            'GET https://sandbox-api.collexia.co/api/v1/transactions/CLX-STATUS-4' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->checkStatus('CLX-STATUS-4');

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Webhook Handling ────────────────────────────────────────────────

    public function test_webhook_maps_completed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'payment.completed',
            'transaction_id' => 'CLX-WEB-1',
            'reference' => 'TEST-REF-WEB-1',
            'status' => 'completed',
            'amount' => 1000.00,
            'currency' => 'NAD',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('payment.completed', $result->eventType);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-WEB-1', $result->providerReference);
        $this->assertSame('TEST-REF-WEB-1', $result->reference);
        $this->assertSame(1000.00, $result->amount);
    }

    public function test_webhook_maps_pending_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'payment.pending',
            'transaction_id' => 'CLX-WEB-2',
            'status' => 'pending',
            'amount' => 500.00,
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_webhook_maps_failed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'payment.failed',
            'transaction_id' => 'CLX-WEB-3',
            'status' => 'rejected',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_webhook_without_reference_is_not_handled(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'payment.completed',
            'status' => 'completed',
        ]);

        $this->assertFalse($result->success);
        $this->assertNull($result->providerReference);
    }

    // ─── Webhook Signature Verification ────────────────────────────────

    public function test_valid_webhook_signature_is_verified(): void
    {
        $payload = json_encode([
            'event_type' => 'payment.completed',
            'transaction_id' => 'CLX-SIG-1',
            'status' => 'completed',
            'amount' => 1000.00,
        ]);

        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $request = Request::create(
            '/api/payments/webhooks/collexia',
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
            'event_type' => 'payment.completed',
            'transaction_id' => 'CLX-SIG-2',
        ]);

        $request = Request::create(
            '/api/payments/webhooks/collexia',
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
        $provider = new CollexiaPaymentProvider(['webhook_secret' => null]);

        $request = Request::create('/api/payments/webhooks/collexia', 'POST', []);

        $this->assertTrue($provider->verifyWebhookSignature($request));
    }

    // ─── End-to-End Webhook Controller Scenarios ─────────────────────────

    protected function createLoan(): Loan
    {
        return Loan::factory()->create();
    }

    protected function createDisbursementTransaction(
        float $amount = 2000.00,
        string $providerReference = 'CLX-DISB-E2E',
    ): DisbursementTransaction {
        return DisbursementTransaction::create([
            'loan_id' => $this->createLoan()->id,
            'direction' => 'outgoing',
            'gross_amount' => $amount,
            'platform_fee' => 100.00,
            'net_amount' => $amount - 100.00,
            'status' => 'awaiting_disbursement',
            'transaction_reference' => 'DISB-'.strtoupper(Str::random(12)),
            'provider_reference' => $providerReference,
        ]);
    }

    protected function createRepayment(
        float $amount = 1000.00,
        string $providerReference = 'CLX-REP-E2E',
    ): Repayment {
        return Repayment::factory()->create([
            'amount' => $amount,
            'status' => 'pending_approval',
            'provider_reference' => $providerReference,
        ]);
    }

    protected function createLenderRepayment(
        float $amount = 1000.00,
        string $providerReference = 'CLX-RET-E2E',
    ): LenderRepayment {
        $repayment = Repayment::factory()->create();
        $lender = User::factory()->create();
        $fundingTransaction = FundingTransaction::factory()->create();

        return LenderRepayment::create([
            'repayment_id' => $repayment->id,
            'lender_id' => $lender->id,
            'funding_transaction_id' => $fundingTransaction->id,
            'amount' => $amount,
            'principal_return' => $amount * 0.8,
            'interest_earned' => $amount * 0.2,
            'penalty_share' => 0,
            'funding_percentage' => 100.00,
            'status' => 'pending',
            'transaction_reference' => 'LRPY-'.strtoupper(Str::random(12)),
            'provider_reference' => $providerReference,
        ]);
    }

    protected function signedPayload(array $payload): array
    {
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, 'test-webhook-secret');

        return [
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    public function test_successful_webhook_updates_disbursement_transaction(): void
    {
        $transaction = $this->createDisbursementTransaction();
        $payload = [
            'provider_event_id' => 'evt-success-1',
            'event_type' => 'payout.completed',
            'transaction_id' => 'CLX-DISB-E2E',
            'reference' => $transaction->transaction_reference,
            'status' => 'completed',
            'amount' => 2000.00,
            'currency' => 'NAD',
        ];
        $signed = $this->signedPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-success-1',
            'provider_reference' => 'CLX-DISB-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'disbursement_transaction',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_pending_webhook_is_recorded_for_repayment(): void
    {
        $repayment = $this->createRepayment();
        $payload = [
            'provider_event_id' => 'evt-pending-1',
            'event_type' => 'collection.pending',
            'transaction_id' => 'CLX-REP-E2E',
            'status' => 'pending',
            'amount' => 1000.00,
            'currency' => 'NAD',
        ];
        $signed = $this->signedPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-pending-1',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'repayment',
            'transaction_id' => $repayment->id,
        ]);
    }

    public function test_failed_webhook_is_recorded_for_lender_return(): void
    {
        $lenderRepayment = $this->createLenderRepayment();
        $payload = [
            'provider_event_id' => 'evt-failed-1',
            'event_type' => 'payout.failed',
            'transaction_id' => 'CLX-RET-E2E',
            'status' => 'failed',
            'amount' => 1000.00,
        ];
        $signed = $this->signedPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-failed-1',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'lender_repayment',
            'transaction_id' => $lenderRepayment->id,
        ]);
    }

    public function test_duplicate_webhook_is_not_processed_twice(): void
    {
        $transaction = $this->createDisbursementTransaction();
        $payload = [
            'provider_event_id' => 'evt-dup-1',
            'event_type' => 'payout.completed',
            'transaction_id' => 'CLX-DISB-E2E',
            'status' => 'completed',
            'amount' => 2000.00,
        ];
        $signed = $this->signedPayload($payload);

        $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ])->assertOk();

        $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ])->assertOk()->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertCount(1, PaymentWebhookEvent::where('provider_event_id', 'evt-dup-1')->get());
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $transaction = $this->createDisbursementTransaction();
        $payload = [
            'provider_event_id' => 'evt-invalid-sig-1',
            'event_type' => 'payout.completed',
            'transaction_id' => 'CLX-DISB-E2E',
            'status' => 'completed',
            'amount' => 2000.00,
        ];

        $response = $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => 'bad-signature',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('payment_webhook_events', [
            'provider_event_id' => 'evt-invalid-sig-1',
        ]);
    }

    public function test_webhook_with_wrong_amount_is_rejected(): void
    {
        $transaction = $this->createDisbursementTransaction(2000.00);
        $payload = [
            'provider_event_id' => 'evt-wrong-amount-1',
            'event_type' => 'payout.completed',
            'transaction_id' => 'CLX-DISB-E2E',
            'status' => 'completed',
            'amount' => 500.00,
        ];
        $signed = $this->signedPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Webhook amount does not match transaction amount.');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-wrong-amount-1',
            'status' => PaymentWebhookEvent::STATUS_INVALID,
        ]);
    }

    public function test_webhook_with_unknown_reference_is_rejected(): void
    {
        $payload = [
            'provider_event_id' => 'evt-unknown-1',
            'event_type' => 'payout.completed',
            'transaction_id' => 'CLX-UNKNOWN',
            'status' => 'completed',
            'amount' => 2000.00,
        ];
        $signed = $this->signedPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/collexia', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No internal transaction found for provider reference.');
    }

    public function test_timeout_is_handled_by_status_check_and_initiation(): void
    {
        $this->fakeCollexiaApi([
            'POST https://sandbox-api.collexia.co/api/v1/collections' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Manual Fallback ─────────────────────────────────────────────────

    public function test_manual_execution_bypasses_collexia_and_preserves_workflow(): void
    {
        config(['payment_providers.default_provider' => 'manual']);

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            paymentMethod: PaymentInstruction::METHOD_MANUAL,
            executionMode: PaymentInstruction::EXECUTION_MANUAL,
            amount: 1000.00,
            reference: 'MANUAL-REF-001',
        );

        $manager = $this->manager();
        $provider = $manager->forInstruction($instruction);

        $this->assertSame('manual', $provider->getName());

        $result = $provider->initiateDisbursement($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
    }

    public function test_unconfigured_collexia_keeps_manual_instructions_on_manual_path(): void
    {
        config([
            'payment_providers.default_provider' => 'collexia',
            'payment_providers.providers.collexia.base_url' => null,
            'payment_providers.providers.collexia.api_key' => null,
        ]);

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
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
