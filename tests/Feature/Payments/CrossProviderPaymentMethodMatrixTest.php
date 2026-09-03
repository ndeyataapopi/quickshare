<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\Exceptions\InvalidPaymentConfigurationException;
use App\Modules\Payments\Providers\CollexiaPaymentProvider;
use App\Modules\Payments\Providers\MobiDebitPaymentProvider;
use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Payments\Providers\RealPayPaymentProvider;
use App\Modules\Payments\Services\PaymentConfigurationResolver;
use App\Modules\Payments\Services\PaymentExecutionOrchestrator;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossProviderPaymentMethodMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    protected array $fakeResponses = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config(['payment_providers.automation_enabled' => true]);

        $this->configureAllProviders();
        $this->resetOperationsToManual();
        $this->fakeResponses = [];
    }

    protected function tearDown(): void
    {
        $this->fakeResponses = [];

        parent::tearDown();
    }

    protected function configureAllProviders(): void
    {
        config([
            'payment_providers.providers.collexia' => [
                'driver' => 'collexia',
                'base_url' => 'https://sandbox-api.collexia.co',
                'api_key' => 'collexia_test_key',
                'client_code' => 'collexia_client',
                'sandbox' => true,
                'timeout' => 30,
                'connection_timeout' => 5,
                'webhook_secret' => 'collexia-secret',
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
            'payment_providers.providers.mobidebit' => [
                'driver' => 'mobidebit',
                'base_url' => 'https://test.mobipaid.io',
                'api_key' => 'mobidebit_test_key',
                'sandbox' => true,
                'timeout' => 30,
                'connection_timeout' => 5,
                'redirect_url' => 'https://quickshare.example.com/payment/receipt',
                'response_url' => 'https://quickshare.example.com/api/payments/webhooks/mobidebit',
                'webhook_secret' => 'mobidebit-secret',
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
            'payment_providers.providers.realpay' => [
                'driver' => 'realpay',
                'base_url' => 'https://sandbox-api.realpay.co.za',
                'api_key' => 'realpay_test_key',
                'sandbox' => true,
                'timeout' => 30,
                'connection_timeout' => 5,
                'auth_header' => 'X-API-Key',
                'webhook_secret' => 'realpay-secret',
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

    protected function resetOperationsToManual(): void
    {
        foreach (PaymentInstruction::operations() as $operation) {
            config(["payment_providers.operations.{$operation}" => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ]]);
        }
    }

    protected function configureOperation(string $operation, string $method, string $provider): void
    {
        config(["payment_providers.operations.{$operation}" => [
            'enabled' => true,
            'method' => $method,
            'mode' => 'automated',
            'provider' => $provider,
        ]]);
    }

    protected function resolver(): PaymentConfigurationResolver
    {
        return app(PaymentConfigurationResolver::class);
    }

    protected function orchestrator(): PaymentExecutionOrchestrator
    {
        return app(PaymentExecutionOrchestrator::class);
    }

    protected function manager(): PaymentProviderManager
    {
        return app(PaymentProviderManager::class);
    }

    protected function fakeProviderApi(string $provider, string $method, string $url, mixed $response): void
    {
        $this->fakeResponses[$method.' '.$url] = $response;

        Http::fake(function ($request) {
            $key = $request->method().' '.$request->url();

            foreach ($this->fakeResponses as $pattern => $response) {
                if ($key === $pattern) {
                    return $response;
                }
            }

            return Http::response(['status' => 'unknown'], 200);
        });
    }

    protected function createFundingTransaction(float $amount = 1000.00): FundingTransaction
    {
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
        ]);
    }

    protected function createAwaitingDisbursement(float $amount = 1000.00): DisbursementTransaction
    {
        $loan = Loan::factory()->create();
        $loan->update(['status' => 'funded', 'funded_amount' => $amount]);

        return DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'gross_amount' => $amount,
            'platform_fee' => 0,
            'net_amount' => $amount,
            'status' => 'awaiting_disbursement',
            'transaction_reference' => 'DISB-'.strtoupper(Str::random(12)),
            'payment_method' => 'bank_transfer',
        ]);
    }

    protected function createPendingRepayment(float $amount = 1000.00): Repayment
    {
        return Repayment::factory()->create([
            'amount' => $amount,
            'status' => 'pending_approval',
            'payment_method' => 'debit_order',
            'transaction_reference' => 'REP-'.strtoupper(Str::random(12)),
        ]);
    }

    protected function createPendingLenderReturn(float $amount = 1000.00): LenderRepayment
    {
        $lender = User::factory()->active()->create();
        $loan = Loan::factory()->create();
        $fundingTransaction = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => $amount,
            'interest_rate' => 15,
            'expected_return' => $amount * 1.15,
            'status' => 'confirmed',
            'transaction_reference' => 'FUND-'.strtoupper(Str::random(12)),
        ]);
        $repayment = Repayment::factory()->create([
            'loan_id' => $loan->id,
            'amount' => $amount,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
        ]);

        return LenderRepayment::create([
            'repayment_id' => $repayment->id,
            'lender_id' => $lender->id,
            'funding_transaction_id' => $fundingTransaction->id,
            'amount' => $amount,
            'principal_return' => $amount,
            'interest_earned' => 0,
            'funding_percentage' => 100,
            'status' => 'pending',
            'transaction_reference' => 'LRET-'.strtoupper(Str::random(12)),
        ]);
    }

    // ─── Capability Matrix from Discovery Docs ─────────────────────────

    public function test_collexia_capability_matrix_matches_documentation(): void
    {
        $provider = $this->manager()->resolve('collexia');

        $this->assertInstanceOf(CollexiaPaymentProvider::class, $provider);

        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT));

        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_WALLET_PAYOUT));
    }

    public function test_mobidebit_capability_matrix_matches_documentation(): void
    {
        $provider = $this->manager()->resolve('mobidebit');

        $this->assertInstanceOf(MobiDebitPaymentProvider::class, $provider);

        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER));

        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER));
    }

    public function test_realpay_capability_matrix_matches_documentation(): void
    {
        $provider = $this->manager()->resolve('realpay');

        $this->assertInstanceOf(RealPayPaymentProvider::class, $provider);

        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER));
        $this->assertTrue($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT));

        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_WALLET_PAYOUT));
        $this->assertFalse($provider->supports(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_WALLET_PAYOUT));
    }

    // ─── Lender Funding ──────────────────────────────────────────────────

    public function test_lender_funding_manual_is_supported_and_preserves_state(): void
    {
        $transaction = $this->createFundingTransaction();

        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
        $this->assertDatabaseHas('funding_transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);
    }

    public function test_lender_funding_via_mobidebit_payment_link_creates_transaction(): void
    {
        $transaction = $this->createFundingTransaction();

        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
            'mobidebit'
        );

        $this->fakeProviderApi('mobidebit', 'POST', 'https://test.mobipaid.io/v2/payment-requests/', Http::response([
            'transaction_id' => 'MBD-FUND-001',
            'reference_number' => $transaction->transaction_reference,
            'status' => 'success',
            'amount' => (string) $transaction->amount,
            'currency' => 'NAD',
            'short_url' => 'https://pay.test/MBD-FUND-001',
        ], 200));

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-FUND-001', $result->providerReference);
        $this->assertSame('mobidebit', $transaction->fresh()->provider);
        $this->assertSame('MBD-FUND-001', $transaction->fresh()->provider_reference);
    }

    public function test_lender_funding_via_realpay_debit_order_creates_transaction(): void
    {
        $transaction = $this->createFundingTransaction();

        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'realpay'
        );

        $this->fakeProviderApi('realpay', 'POST', 'https://sandbox-api.realpay.co.za/api/v1/collections', Http::response([
            'transaction_id' => 'RP-FUND-001',
            'reference' => $transaction->transaction_reference,
            'status' => 'successful',
            'amount' => (string) $transaction->amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-FUND-001', $result->providerReference);
        $this->assertSame('realpay', $transaction->fresh()->provider);
    }

    public function test_lender_funding_unsupported_method_fails_safely(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'mobidebit'
        );

        $this->expectException(InvalidPaymentConfigurationException::class);

        $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
    }

    // ─── Borrower Disbursement ─────────────────────────────────────────

    public function test_borrower_disbursement_manual_is_supported(): void
    {
        $transaction = $this->createAwaitingDisbursement();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );

        $result = $this->orchestrator()->executeDisbursement($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
    }

    public function test_borrower_disbursement_via_collexia_bank_payout(): void
    {
        $transaction = $this->createAwaitingDisbursement();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'collexia'
        );

        $this->fakeProviderApi('collexia', 'POST', 'https://sandbox-api.collexia.co/api/v1/payments', Http::response([
            'transaction_id' => 'CLX-DISB-001',
            'status' => 'successful',
            'amount' => (string) $transaction->gross_amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeDisbursement($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-DISB-001', $result->providerReference);
        $this->assertSame('collexia', $transaction->fresh()->provider);
    }

    public function test_borrower_disbursement_via_realpay_bank_payout(): void
    {
        $transaction = $this->createAwaitingDisbursement();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'realpay'
        );

        $this->fakeProviderApi('realpay', 'POST', 'https://sandbox-api.realpay.co.za/api/v1/payouts', Http::response([
            'transaction_id' => 'RP-DISB-001',
            'status' => 'successful',
            'amount' => (string) $transaction->gross_amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeDisbursement($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-DISB-001', $result->providerReference);
        $this->assertSame('realpay', $transaction->fresh()->provider);
    }

    public function test_borrower_disbursement_unsupported_method_fails_safely(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'mobidebit'
        );

        $this->expectException(InvalidPaymentConfigurationException::class);

        $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
    }

    // ─── Borrower Repayment ───────────────────────────────────────────────

    public function test_borrower_repayment_manual_is_supported(): void
    {
        $repayment = $this->createPendingRepayment();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
    }

    public function test_borrower_repayment_via_collexia_debit_order(): void
    {
        $repayment = $this->createPendingRepayment();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'collexia'
        );

        $this->fakeProviderApi('collexia', 'POST', 'https://sandbox-api.collexia.co/api/v1/collections', Http::response([
            'transaction_id' => 'CLX-REP-001',
            'status' => 'successful',
            'amount' => (string) $repayment->amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-REP-001', $result->providerReference);
        $this->assertSame('collexia', $repayment->fresh()->provider);
    }

    public function test_borrower_repayment_via_mobidebit_payment_link(): void
    {
        $repayment = $this->createPendingRepayment();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
            'mobidebit'
        );

        $this->fakeProviderApi('mobidebit', 'POST', 'https://test.mobipaid.io/v2/payment-requests/', Http::response([
            'transaction_id' => 'MBD-REP-001',
            'reference_number' => $repayment->transaction_reference,
            'status' => 'success',
            'amount' => (string) $repayment->amount,
            'currency' => 'NAD',
            'short_url' => 'https://pay.test/MBD-REP-001',
        ], 200));

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-REP-001', $result->providerReference);
        $this->assertSame('mobidebit', $repayment->fresh()->provider);
    }

    public function test_borrower_repayment_via_mobidebit_debit_order(): void
    {
        $repayment = $this->createPendingRepayment();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'mobidebit'
        );

        $this->fakeProviderApi('mobidebit', 'POST', 'https://test.mobipaid.io/v2/payment-requests/', Http::response([
            'transaction_id' => 'MBD-REP-002',
            'reference_number' => $repayment->transaction_reference,
            'status' => 'success',
            'amount' => (string) $repayment->amount,
            'currency' => 'NAD',
            'payment_type' => 'DB',
            'short_url' => 'https://pay.test/MBD-REP-002',
        ], 200));

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('MBD-REP-002', $result->providerReference);
    }

    public function test_borrower_repayment_via_realpay_debit_order(): void
    {
        $repayment = $this->createPendingRepayment();

        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'realpay'
        );

        $this->fakeProviderApi('realpay', 'POST', 'https://sandbox-api.realpay.co.za/api/v1/collections', Http::response([
            'transaction_id' => 'RP-REP-001',
            'status' => 'successful',
            'amount' => (string) $repayment->amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-REP-001', $result->providerReference);
    }

    public function test_borrower_repayment_unsupported_method_fails_safely(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'collexia'
        );

        $this->expectException(InvalidPaymentConfigurationException::class);

        $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT);
    }

    // ─── Lender Returns ──────────────────────────────────────────────────

    public function test_lender_returns_manual_is_supported(): void
    {
        $lenderRepayment = $this->createPendingLenderReturn();

        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
    }

    public function test_lender_returns_via_collexia_bank_payout(): void
    {
        $lenderRepayment = $this->createPendingLenderReturn();

        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'collexia'
        );

        $this->fakeProviderApi('collexia', 'POST', 'https://sandbox-api.collexia.co/api/v1/payments', Http::response([
            'transaction_id' => 'CLX-RET-001',
            'status' => 'successful',
            'amount' => (string) $lenderRepayment->amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('CLX-RET-001', $result->providerReference);
        $this->assertSame('collexia', $lenderRepayment->fresh()->provider);
    }

    public function test_lender_returns_via_realpay_bank_payout(): void
    {
        $lenderRepayment = $this->createPendingLenderReturn();

        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'realpay'
        );

        $this->fakeProviderApi('realpay', 'POST', 'https://sandbox-api.realpay.co.za/api/v1/payouts', Http::response([
            'transaction_id' => 'RP-RET-001',
            'status' => 'successful',
            'amount' => (string) $lenderRepayment->amount,
            'currency' => 'NAD',
        ], 200));

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertSame('RP-RET-001', $result->providerReference);
        $this->assertSame('realpay', $lenderRepayment->fresh()->provider);
    }

    public function test_lender_returns_unsupported_method_fails_safely(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'mobidebit'
        );

        $this->expectException(InvalidPaymentConfigurationException::class);

        $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_RETURN);
    }

    // ─── Operation Independence ──────────────────────────────────────────

    public function test_payment_method_for_one_operation_does_not_affect_others(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'collexia'
        );
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'realpay'
        );
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'realpay'
        );

        $lenderFunding = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
        $disbursement = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
        $repayment = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT);
        $lenderReturn = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_RETURN);

        $this->assertSame(PaymentInstruction::METHOD_MANUAL, $lenderFunding->method);
        $this->assertSame('manual', $lenderFunding->provider);

        $this->assertSame(PaymentInstruction::METHOD_BANK_PAYOUT, $disbursement->method);
        $this->assertSame('collexia', $disbursement->provider);

        $this->assertSame(PaymentInstruction::METHOD_DEBIT_ORDER, $repayment->method);
        $this->assertSame('realpay', $repayment->provider);

        $this->assertSame(PaymentInstruction::METHOD_BANK_PAYOUT, $lenderReturn->method);
        $this->assertSame('realpay', $lenderReturn->provider);
    }

    // ─── Mixed-Method End-to-End Scenario ────────────────────────────────

    public function test_mixed_method_scenario_runs_all_operations_independently(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'collexia'
        );
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'mobidebit'
        );
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'realpay'
        );

        $fundingTransaction = $this->createFundingTransaction();
        $disbursementTransaction = $this->createAwaitingDisbursement();
        $repayment = $this->createPendingRepayment();
        $lenderRepayment = $this->createPendingLenderReturn();

        $this->fakeProviderApi('collexia', 'POST', 'https://sandbox-api.collexia.co/api/v1/payments', Http::response([
            'transaction_id' => 'CLX-DISB-MIXED',
            'status' => 'successful',
            'amount' => (string) $disbursementTransaction->gross_amount,
            'currency' => 'NAD',
        ], 200));

        $this->fakeProviderApi('mobidebit', 'POST', 'https://test.mobipaid.io/v2/payment-requests/', Http::response([
            'transaction_id' => 'MBD-REP-MIXED',
            'reference_number' => $repayment->transaction_reference,
            'status' => 'success',
            'amount' => (string) $repayment->amount,
            'currency' => 'NAD',
            'payment_type' => 'DB',
            'short_url' => 'https://pay.test/MBD-REP-MIXED',
        ], 200));

        $this->fakeProviderApi('realpay', 'POST', 'https://sandbox-api.realpay.co.za/api/v1/payouts', Http::response([
            'transaction_id' => 'RP-RET-MIXED',
            'status' => 'successful',
            'amount' => (string) $lenderRepayment->amount,
            'currency' => 'NAD',
        ], 200));

        $fundingResult = $this->orchestrator()->executeFunding($fundingTransaction);
        $disbursementResult = $this->orchestrator()->executeDisbursement($disbursementTransaction);
        $repaymentResult = $this->orchestrator()->executeRepayment($repayment);
        $lenderReturnResult = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertTrue($fundingResult->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $fundingResult->status);
        $this->assertSame('manual', $fundingResult->providerName);

        $this->assertTrue($disbursementResult->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $disbursementResult->status);
        $this->assertSame('CLX-DISB-MIXED', $disbursementResult->providerReference);
        $this->assertSame('collexia', $disbursementTransaction->fresh()->provider);

        $this->assertTrue($repaymentResult->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $repaymentResult->status);
        $this->assertSame('MBD-REP-MIXED', $repaymentResult->providerReference);
        $this->assertSame('mobidebit', $repayment->fresh()->provider);

        $this->assertTrue($lenderReturnResult->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $lenderReturnResult->status);
        $this->assertSame('RP-RET-MIXED', $lenderReturnResult->providerReference);
        $this->assertSame('realpay', $lenderRepayment->fresh()->provider);
    }

    // ─── Duplicate Event Safety ─────────────────────────────────────────

    public function test_duplicate_webhook_cannot_duplicate_lender_return(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'realpay'
        );

        $lenderRepayment = $this->createPendingLenderReturn();
        $lenderRepayment->update([
            'provider_reference' => 'RP-RET-DUP',
            'provider' => 'realpay',
        ]);

        $payload = [
            'event_type' => 'transaction.completed',
            'event_id' => 'RP-EVT-001',
            'transaction_id' => 'RP-RET-DUP',
            'reference' => $lenderRepayment->transaction_reference,
            'status' => 'successful',
            'amount' => (string) $lenderRepayment->amount,
        ];
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, 'realpay-secret');

        $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signature,
        ])->assertOk();

        $firstStatus = $lenderRepayment->fresh()->status;
        $this->assertSame('processed', $firstStatus);

        $this->postJson('/api/payments/webhooks/realpay', $payload, [
            'X-Webhook-Signature' => $signature,
        ])->assertOk()->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertSame('processed', $lenderRepayment->fresh()->status);
    }

    public function test_duplicate_webhook_cannot_duplicate_repayment_allocation(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_PAYMENT_LINK,
            'mobidebit'
        );

        $repayment = $this->createPendingRepayment();
        $repayment->update([
            'provider_reference' => 'MBD-REP-DUP',
            'provider' => 'mobidebit',
        ]);

        $payload = [
            'response' => json_encode([
                'result' => 'ACK',
                'result_code' => '000.000.000',
                'payment_id' => 'MBD-EVT-001',
                'transaction_id' => 'MBD-REP-DUP',
                'reference_number' => $repayment->transaction_reference,
                'amount' => (string) $repayment->amount,
                'currency' => 'NAD',
            ]),
        ];
        $json = json_encode($payload);
        $signature = hash_hmac('sha256', $json, 'mobidebit-secret');

        $this->postJson('/api/payments/webhooks/mobidebit', $payload, [
            'X-Webhook-Signature' => $signature,
        ])->assertOk();

        $this->assertSame('paid', $repayment->fresh()->status);

        $lenderRepaymentsCount = LenderRepayment::where('repayment_id', $repayment->id)->count();

        $this->postJson('/api/payments/webhooks/mobidebit', $payload, [
            'X-Webhook-Signature' => $signature,
        ])->assertOk()->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertSame('paid', $repayment->fresh()->status);
        $this->assertSame($lenderRepaymentsCount, LenderRepayment::where('repayment_id', $repayment->id)->count());
    }

    // ─── Failure / Retry Safety ──────────────────────────────────────────

    public function test_failed_disbursement_does_not_advance_status(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            'collexia'
        );

        $transaction = $this->createAwaitingDisbursement();

        $this->fakeProviderApi('collexia', 'POST', 'https://sandbox-api.collexia.co/api/v1/payments', Http::response([
            'status' => 'failed',
            'message' => 'Invalid beneficiary',
        ], 422));

        $result = $this->orchestrator()->executeDisbursement($transaction);

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
        $this->assertSame('awaiting_disbursement', $transaction->fresh()->status);
    }

    public function test_timeout_does_not_corrupt_business_state(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            'realpay'
        );

        $repayment = $this->createPendingRepayment();

        $this->fakeProviderApi('realpay', 'POST', 'https://sandbox-api.realpay.co.za/api/v1/collections', function () {
            throw new ConnectionException('cURL error 28');
        });

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
        $this->assertSame('pending_approval', $repayment->fresh()->status);
    }

    // ─── Manual Fallback Remains Functional ─────────────────────────────

    public function test_manual_mode_bypasses_all_providers_and_preserves_workflow(): void
    {
        $this->configureOperation(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_MANUAL,
            'manual'
        );

        $transaction = $this->createFundingTransaction();

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('manual', $result->providerName);
        $this->assertNull($transaction->fresh()->provider_reference);
    }
}
