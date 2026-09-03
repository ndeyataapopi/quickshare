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
use App\Modules\Payments\Providers\ManualPaymentProvider;
use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Payments\Providers\RealPayPaymentProvider;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealPayPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->configureRealPay();
    }

    protected function configureRealPay(): void
    {
        config([
            'payment_providers.default_provider' => 'realpay',
            'payment_providers.providers.realpay' => [
                'driver' => 'realpay',
                'base_url' => 'https://sandbox-api.realpay.co.za',
                'api_key' => 'rp_test_key',
                'sandbox' => true,
                'timeout' => 30,
                'connection_timeout' => 5,
                'auth_header' => 'X-API-Key',
                'webhook_secret' => 'test-webhook-secret',
                'signature_header' => 'X-Webhook-Signature',
                'signature_algorithm' => 'hmac-sha256',
                'health_endpoint' => null,
                'endpoints' => [
                    'collections' => '/api/v1/collections',
                    'payouts' => '/api/v1/payouts',
                    'verification' => '/api/v1/verifications',
                    'status_check' => '/api/v1/transactions/{reference}',
                ],
                'supported_methods' => [
                    'lender_funding' => ['debit_order'],
                    'borrower_disbursement' => ['bank_payout'],
                    'borrower_repayment' => ['debit_order'],
                    'lender_returns' => ['bank_payout'],
                ],
            ],
        ]);
    }

    protected function provider(): RealPayPaymentProvider
    {
        return new RealPayPaymentProvider(config('payment_providers.providers.realpay'));
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
        ?array $sourceAccount = null,
        ?array $destinationAccount = null,
    ): PaymentInstruction {
        return new PaymentInstruction(
            operation: $operation,
            paymentMethod: $paymentMethod,
            executionMode: PaymentInstruction::EXECUTION_AUTOMATED,
            amount: $amount,
            reference: $reference,
            currency: 'NAD',
            sourceAccount: $sourceAccount,
            destinationAccount: $destinationAccount,
        );
    }

    protected function fakeRealPayApi(array $responses): void
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

    public function test_realpay_provider_is_registered_and_implements_interface(): void
    {
        $provider = $this->manager()->resolve('realpay');

        $this->assertInstanceOf(RealPayPaymentProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertSame('realpay', $provider->getName());
    }

    public function test_provider_reports_configured_when_credentials_present(): void
    {
        $this->assertTrue($this->provider()->isConfigured());
        $this->assertTrue($this->provider()->isHealthy());
    }

    public function test_provider_reports_not_configured_when_missing_credentials(): void
    {
        $provider = new RealPayPaymentProvider(['base_url' => null, 'api_key' => null]);

        $this->assertFalse($provider->isConfigured());
        $this->assertFalse($provider->isHealthy());
    }

    // ─── Capability Matrix ───────────────────────────────────────────────

    public function test_supports_confirmed_capabilities(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_MANUAL));
    }

    public function test_does_not_support_unconfirmed_methods(): void
    {
        $provider = $this->provider();

        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_WALLET_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_WALLET_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_BANK_PAYOUT));
    }

    // ─── Lender Funding via Debit Order ──────────────────────────────────

    public function test_successful_lender_funding_collection_returns_completed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => Http::response([
                'transaction_id' => 'RP-FUND-123',
                'reference' => 'TEST-REF-001',
                'status' => 'successful',
                'amount' => '1000.00',
                'currency' => 'NAD',
            ], 200),
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            sourceAccount: ['account_number' => '1234567890', 'branch_code' => '000000'],
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-FUND-123', $result->providerReference);
        $this->assertSame('TEST-REF-001', $result->externalReference);
    }

    public function test_pending_lender_funding_collection_returns_pending(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => Http::response([
                'transaction_id' => 'RP-FUND-456',
                'reference' => 'TEST-REF-001',
                'status' => 'pending',
            ], 200),
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
        $this->assertSame('RP-FUND-456', $result->providerReference);
    }

    public function test_failed_lender_funding_collection_returns_failed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid mandate',
            ], 422),
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_lender_funding_collection_timeout_returns_timeout(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Borrower Disbursement via Bank Payout ───────────────────────────

    public function test_successful_borrower_disbursement_returns_completed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'transaction_id' => 'RP-DISB-123',
                'reference' => 'TEST-REF-001',
                'status' => 'successful',
                'amount' => '1000.00',
                'currency' => 'NAD',
            ], 200),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '0987654321', 'branch_code' => '999999'],
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-DISB-123', $result->providerReference);
    }

    public function test_pending_borrower_disbursement_returns_pending(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'transaction_id' => 'RP-DISB-456',
                'reference' => 'TEST-REF-001',
                'status' => 'queued',
            ], 200),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '0987654321', 'branch_code' => '999999'],
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_borrower_disbursement_invalid_beneficiary_returns_failed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/verifications' => Http::response([
                'reference' => 'TEST-REF-001-verify',
                'valid' => false,
                'reason' => 'Account not found',
            ], 200),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '0000000000', 'branch_code' => '000000'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('beneficiary', strtolower($result->message));
    }

    public function test_borrower_disbursement_provider_reports_invalid_beneficiary(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid beneficiary account',
                'errors' => [
                    'destination_account' => ['Account does not exist'],
                ],
            ], 422),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '0000000000', 'branch_code' => '000000'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('beneficiary', strtolower($result->message));
    }

    public function test_borrower_disbursement_invalid_amount_returns_failed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid amount',
            ], 422),
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            amount: 0,
            destinationAccount: ['account_number' => '0987654321', 'branch_code' => '999999'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_borrower_disbursement_timeout_returns_timeout(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateDisbursement($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '0987654321', 'branch_code' => '999999'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Borrower Repayment via Debit Order ───────────────────────────────

    public function test_successful_borrower_repayment_collection_returns_completed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => Http::response([
                'transaction_id' => 'RP-REP-123',
                'reference' => 'TEST-REF-001',
                'status' => 'successful',
                'amount' => '1000.00',
                'currency' => 'NAD',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            sourceAccount: ['account_number' => '1234567890', 'branch_code' => '000000'],
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-REP-123', $result->providerReference);
    }

    public function test_pending_borrower_repayment_collection_returns_pending(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => Http::response([
                'transaction_id' => 'RP-REP-456',
                'reference' => 'TEST-REF-001',
                'status' => 'submitted',
            ], 200),
        ]);

        $result = $this->provider()->initiateRepayment($this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_failed_borrower_repayment_collection_returns_failed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => Http::response([
                'status' => 'failed',
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

    public function test_borrower_repayment_collection_timeout_returns_timeout(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/collections' => function () {
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

    // ─── Lender Return via Bank Payout ───────────────────────────────────

    public function test_successful_lender_return_returns_completed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'transaction_id' => 'RP-RET-123',
                'reference' => 'TEST-REF-001',
                'status' => 'successful',
                'amount' => '1000.00',
                'currency' => 'NAD',
            ], 200),
        ]);

        $result = $this->provider()->initiateLenderReturn($this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '1122334455', 'branch_code' => '111111'],
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-RET-123', $result->providerReference);
    }

    public function test_pending_lender_return_returns_pending(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'transaction_id' => 'RP-RET-456',
                'reference' => 'TEST-REF-001',
                'status' => 'processing',
            ], 200),
        ]);

        $result = $this->provider()->initiateLenderReturn($this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '1122334455', 'branch_code' => '111111'],
        ));

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_failed_lender_return_returns_failed(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => Http::response([
                'status' => 'failed',
                'message' => 'Invalid destination account',
            ], 422),
        ]);

        $result = $this->provider()->initiateLenderReturn($this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '0000000000', 'branch_code' => '000000'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_lender_return_timeout_returns_timeout(): void
    {
        $this->fakeRealPayApi([
            'POST https://sandbox-api.realpay.co.za/api/v1/payouts' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->initiateLenderReturn($this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            destinationAccount: ['account_number' => '1122334455', 'branch_code' => '111111'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Status Check ────────────────────────────────────────────────────

    public function test_status_check_maps_completed_status(): void
    {
        $this->fakeRealPayApi([
            'GET https://sandbox-api.realpay.co.za/api/v1/transactions/RP-STATUS-1' => Http::response([
                'status' => 'successful',
                'transaction_id' => 'RP-STATUS-1',
                'amount' => 1000.00,
                'currency' => 'NAD',
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('RP-STATUS-1');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
    }

    public function test_status_check_maps_pending_status(): void
    {
        $this->fakeRealPayApi([
            'GET https://sandbox-api.realpay.co.za/api/v1/transactions/RP-STATUS-2' => Http::response([
                'status' => 'processing',
                'transaction_id' => 'RP-STATUS-2',
            ], 200),
        ]);

        $result = $this->provider()->checkStatus('RP-STATUS-2');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_status_check_failure_returns_failed(): void
    {
        $this->fakeRealPayApi([
            'GET https://sandbox-api.realpay.co.za/api/v1/transactions/RP-STATUS-3' => Http::response([
                'status' => 'failed',
                'transaction_id' => 'RP-STATUS-3',
            ], 422),
        ]);

        $result = $this->provider()->checkStatus('RP-STATUS-3');

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_status_check_timeout_returns_timeout(): void
    {
        $this->fakeRealPayApi([
            'GET https://sandbox-api.realpay.co.za/api/v1/transactions/RP-STATUS-4' => function () {
                throw new ConnectionException('cURL error 28');
            },
        ]);

        $result = $this->provider()->checkStatus('RP-STATUS-4');

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    // ─── Webhook Parsing ──────────────────────────────────────────────────

    public function test_webhook_maps_completed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-WEB-1',
            'reference' => 'TEST-REF-WEB-1',
            'status' => 'successful',
            'amount' => '1000.00',
            'currency' => 'NAD',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('transaction.completed', $result->eventType);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-WEB-1', $result->providerReference);
        $this->assertSame('TEST-REF-WEB-1', $result->reference);
        $this->assertSame(1000.00, $result->amount);
    }

    public function test_webhook_maps_pending_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'transaction.pending',
            'transaction_id' => 'RP-WEB-2',
            'reference' => 'TEST-REF-WEB-2',
            'status' => 'pending',
            'amount' => '1000.00',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
    }

    public function test_webhook_maps_failed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'transaction.failed',
            'transaction_id' => 'RP-WEB-3',
            'reference' => 'TEST-REF-WEB-3',
            'status' => 'failed',
            'amount' => '1000.00',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }

    public function test_webhook_maps_reversed_event(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'transaction.reversed',
            'transaction_id' => 'RP-WEB-4',
            'reference' => 'TEST-REF-WEB-4',
            'status' => 'reversed',
            'amount' => '1000.00',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_REVERSED, $result->status);
    }

    public function test_webhook_without_reference_is_not_handled(): void
    {
        $result = $this->provider()->handleWebhook([
            'event_type' => 'transaction.completed',
            'status' => 'successful',
            'amount' => '1000.00',
        ]);

        $this->assertFalse($result->success);
        $this->assertNull($result->providerReference);
    }

    // ─── Webhook Signature Verification ──────────────────────────────────

    public function test_valid_webhook_signature_is_verified(): void
    {
        $payload = json_encode([
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-SIG-1',
            'status' => 'successful',
        ]);

        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        $request = Request::create(
            '/api/payments/webhooks/realpay',
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
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-SIG-2',
        ]);

        $request = Request::create(
            '/api/payments/webhooks/realpay',
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
        $provider = new RealPayPaymentProvider(['webhook_secret' => null]);

        $request = Request::create('/api/payments/webhooks/realpay', 'POST', []);

        $this->assertTrue($provider->verifyWebhookSignature($request));
    }

    // ─── End-to-End Webhook Controller Scenarios ─────────────────────────

    protected function createFundingTransaction(
        float $amount = 1000.00,
        string $providerReference = 'RP-FUND-E2E',
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

    protected function createDisbursementTransaction(
        float $amount = 1000.00,
        string $providerReference = 'RP-DISB-E2E',
    ): DisbursementTransaction {
        $loan = Loan::factory()->create();

        return DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'gross_amount' => $amount,
            'platform_fee' => 0,
            'net_amount' => $amount,
            'status' => 'processing',
            'transaction_reference' => 'DISB-'.strtoupper(Str::random(12)),
            'provider_reference' => $providerReference,
        ]);
    }

    protected function createRepayment(
        float $amount = 1000.00,
        string $providerReference = 'RP-REP-E2E',
    ): Repayment {
        return Repayment::factory()->create([
            'amount' => $amount,
            'status' => 'pending_approval',
            'provider_reference' => $providerReference,
        ]);
    }

    protected function createLenderRepayment(
        float $amount = 1000.00,
        string $providerReference = 'RP-RET-E2E',
    ): LenderRepayment {
        return LenderRepayment::factory()->create([
            'amount' => $amount,
            'status' => 'pending',
            'provider_reference' => $providerReference,
        ]);
    }

    protected function signedWebhookPayload(array $payload): array
    {
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, 'test-webhook-secret');

        return [
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    public function test_completed_webhook_updates_funding_transaction(): void
    {
        $transaction = $this->createFundingTransaction();
        $payload = [
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-FUND-E2E',
            'reference' => $transaction->transaction_reference,
            'status' => 'successful',
            'amount' => '1000.00',
            'currency' => 'NAD',
        ];
        $signed = $this->signedWebhookPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'RP-FUND-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'funding_transaction',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_pending_webhook_is_recorded_for_repayment(): void
    {
        $repayment = $this->createRepayment();
        $payload = [
            'event_type' => 'transaction.pending',
            'transaction_id' => 'RP-REP-E2E',
            'reference' => $repayment->transaction_reference,
            'status' => 'pending',
            'amount' => '1000.00',
            'currency' => 'NAD',
        ];
        $signed = $this->signedWebhookPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'RP-REP-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'repayment',
            'transaction_id' => $repayment->id,
        ]);
    }

    public function test_failed_webhook_is_recorded_for_disbursement(): void
    {
        $transaction = $this->createDisbursementTransaction();
        $payload = [
            'event_type' => 'transaction.failed',
            'transaction_id' => 'RP-DISB-E2E',
            'reference' => $transaction->transaction_reference,
            'status' => 'failed',
            'amount' => '1000.00',
        ];
        $signed = $this->signedWebhookPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'RP-DISB-E2E',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'disbursement_transaction',
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_duplicate_webhook_is_not_processed_twice(): void
    {
        $repayment = $this->createRepayment();
        $payload = [
            'event_type' => 'transaction.completed',
            'event_id' => 'RP-DUP-1',
            'transaction_id' => 'RP-REP-E2E',
            'reference' => $repayment->transaction_reference,
            'status' => 'successful',
            'amount' => '1000.00',
        ];
        $signed = $this->signedWebhookPayload($payload);

        $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ])->assertOk();

        $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ])->assertOk()->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertCount(1, PaymentWebhookEvent::where('provider_event_id', 'RP-DUP-1')->get());
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $repayment = $this->createRepayment();
        $payload = [
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-REP-E2E',
            'reference' => $repayment->transaction_reference,
            'status' => 'successful',
            'amount' => '1000.00',
        ];

        $response = $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => 'bad-signature',
        ]);

        $response->assertUnauthorized();
    }

    public function test_webhook_with_wrong_amount_is_rejected(): void
    {
        $repayment = $this->createRepayment(1000.00);
        $payload = [
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-REP-E2E',
            'reference' => $repayment->transaction_reference,
            'status' => 'successful',
            'amount' => '500.00',
        ];
        $signed = $this->signedWebhookPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Webhook amount does not match transaction amount.');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_reference' => 'RP-REP-E2E',
            'status' => PaymentWebhookEvent::STATUS_INVALID,
        ]);
    }

    public function test_webhook_with_unknown_reference_is_rejected(): void
    {
        $payload = [
            'event_type' => 'transaction.completed',
            'transaction_id' => 'RP-UNKNOWN',
            'reference' => 'UNKNOWN-REF',
            'status' => 'successful',
            'amount' => '1000.00',
        ];
        $signed = $this->signedWebhookPayload($payload);

        $response = $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signed['signature'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No internal transaction found for provider reference.');
    }

    // ─── Manual Fallback ─────────────────────────────────────────────────

    public function test_manual_execution_bypasses_realpay_and_preserves_workflow(): void
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

    public function test_unconfigured_realpay_keeps_manual_instructions_on_manual_path(): void
    {
        config([
            'payment_providers.default_provider' => 'realpay',
            'payment_providers.providers.realpay.base_url' => null,
            'payment_providers.providers.realpay.api_key' => null,
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
