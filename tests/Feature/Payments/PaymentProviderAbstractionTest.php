<?php

namespace Tests\Feature\Payments;

use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\Providers\FakePaymentProvider;
use App\Modules\Payments\Providers\ManualPaymentProvider;
use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Payments\Services\PaymentExecutionService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentProviderAbstractionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentProvider::clearForcedOutcome();

        // Tests exercise the automated path by default, so default to fake.
        config(['payment_providers.default_provider' => 'fake']);
    }

    protected function manager(): PaymentProviderManager
    {
        return app(PaymentProviderManager::class);
    }

    protected function executionService(): PaymentExecutionService
    {
        return app(PaymentExecutionService::class);
    }

    protected function instruction(
        string $operation,
        string $paymentMethod,
        string $executionMode,
        float $amount = 1000.00,
        string $reference = 'TEST-REF-001',
    ): PaymentInstruction {
        return new PaymentInstruction(
            operation: $operation,
            paymentMethod: $paymentMethod,
            executionMode: $executionMode,
            amount: $amount,
            reference: $reference,
            currency: 'NAD',
            loanId: 1,
            userId: 1,
        );
    }

    // ─── Provider Resolution ─────────────────────────────────────────

    public function test_manual_provider_can_be_selected(): void
    {
        $provider = $this->manager()->resolve('manual');

        $this->assertInstanceOf(ManualPaymentProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertTrue($provider->isConfigured());
        $this->assertTrue($provider->isHealthy());
    }

    public function test_fake_provider_can_be_selected(): void
    {
        $provider = $this->manager()->resolve('fake');

        $this->assertInstanceOf(FakePaymentProvider::class, $provider);
        $this->assertInstanceOf(PaymentProviderInterface::class, $provider);
        $this->assertTrue($provider->isConfigured());
        $this->assertTrue($provider->isHealthy());
    }

    public function test_unregistered_provider_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment provider [not_a_provider] is not registered.');

        $this->manager()->resolve('not_a_provider');
    }

    // ─── Operation Routing ─────────────────────────────────────────────

    public function test_lender_funding_resolves_through_abstraction(): void
    {
        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertSame('fake', $result->providerName);
        $this->assertTrue($result->success);
    }

    public function test_borrower_disbursement_resolves_through_abstraction(): void
    {
        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertSame('fake', $result->providerName);
    }

    public function test_borrower_repayment_resolves_through_abstraction(): void
    {
        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertSame('fake', $result->providerName);
    }

    public function test_lender_return_resolves_through_abstraction(): void
    {
        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertSame('fake', $result->providerName);
    }

    // ─── Payment Method Independence ───────────────────────────────────

    public function test_payment_methods_are_independent_per_operation(): void
    {
        $operations = PaymentInstruction::operations();
        $methods = [
            PaymentInstruction::METHOD_PAYMENT_LINK,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            PaymentInstruction::METHOD_WALLET_PAYOUT,
        ];

        foreach ($operations as $operation) {
            foreach ($methods as $method) {
                $instruction = $this->instruction($operation, $method, PaymentInstruction::EXECUTION_AUTOMATED);
                $result = $this->executionService()->execute($instruction);

                $this->assertInstanceOf(PaymentResult::class, $result, "Failed for operation {$operation} method {$method}");
                $this->assertSame('fake', $result->providerName);
            }
        }
    }

    public function test_manual_execution_routes_to_manual_provider_regardless_of_method(): void
    {
        $operations = PaymentInstruction::operations();

        foreach ($operations as $operation) {
            $instruction = $this->instruction($operation, PaymentInstruction::METHOD_BANK_PAYOUT, PaymentInstruction::EXECUTION_MANUAL);
            $result = $this->executionService()->execute($instruction);

            $this->assertTrue($result->success);
            $this->assertSame('manual', $result->providerName);
            $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        }
    }

    // ─── Unsupported Methods ───────────────────────────────────────────

    public function test_unsupported_methods_are_rejected_cleanly(): void
    {
        // The fake provider does not support a hypothetical unknown method because
        // PaymentInstruction validates the method before reaching the provider.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported payment method: crypto');

        $this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            'crypto',
            PaymentInstruction::EXECUTION_AUTOMATED,
        );
    }

    public function test_unsupported_operation_is_rejected_cleanly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported payment operation: refund');

        $this->instruction(
            'refund',
            PaymentInstruction::METHOD_MANUAL,
            PaymentInstruction::EXECUTION_MANUAL,
        );
    }

    // ─── Fake Provider Outcomes ──────────────────────────────────────────

    public function test_fake_success_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_SUCCESS);

        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_COMPLETED, $result->status);
        $this->assertStringStartsWith('FAKE-', $result->providerReference);
        $this->assertSame('fake', $result->providerName);
    }

    public function test_fake_pending_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_PENDING);

        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
        $this->assertTrue($result->isPending());
    }

    public function test_fake_failure_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_FAILED);

        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
        $this->assertTrue($result->isFailed());
    }

    public function test_fake_timeout_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_TIMEOUT);

        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_LENDER_RETURN,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $result->status);
    }

    public function test_fake_reversal_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_REVERSED);

        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            PaymentInstruction::METHOD_WALLET_PAYOUT,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_REVERSED, $result->status);
    }

    public function test_fake_duplicate_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_DUPLICATE);

        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            PaymentInstruction::EXECUTION_AUTOMATED,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_DUPLICATE, $result->status);
        $this->assertTrue($result->metadata['duplicate'] ?? false);
    }

    public function test_fake_webhook_duplicate_works(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_WEBHOOK_DUPLICATE);

        $provider = $this->manager()->resolve('fake');

        $webhook = $provider->handleWebhook([
            'reference' => 'TEST-REF-WEBHOOK',
            'provider_reference' => 'FAKE-123',
            'amount' => 1000.00,
            'currency' => 'NAD',
        ]);

        $this->assertTrue($webhook->success);
        $this->assertSame('webhook.duplicate', $webhook->eventType);
        $this->assertSame(PaymentResult::STATUS_DUPLICATE, $webhook->status);
    }

    // ─── Manual Provider Behavior ──────────────────────────────────────

    public function test_manual_provider_does_not_move_money(): void
    {
        $instruction = $this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_MANUAL,
            PaymentInstruction::EXECUTION_MANUAL,
        );

        $result = $this->executionService()->execute($instruction);

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertNull($result->providerReference);
        $this->assertStringContainsString('Money moves outside QuickShare', $result->message);
    }

    public function test_manual_provider_supports_only_manual_method(): void
    {
        $manual = $this->manager()->resolve('manual');

        $this->assertTrue($manual->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_MANUAL));
        $this->assertFalse($manual->supports(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK));
    }

    public function test_manual_provider_webhook_signature_always_false(): void
    {
        $manual = $this->manager()->resolve('manual');
        $request = Request::create('/webhooks/manual', 'POST', []);

        $this->assertFalse($manual->verifyWebhookSignature($request));
        $this->assertFalse($manual->handleWebhook([])->success);
    }

    // ─── Manager / Service Defaults ────────────────────────────────────

    public function test_default_provider_is_resolvable(): void
    {
        $default = $this->manager()->default();

        $this->assertInstanceOf(PaymentProviderInterface::class, $default);
    }

    public function test_check_status_returns_expected_fake_result(): void
    {
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_PENDING);

        $provider = $this->manager()->resolve('fake');
        $result = $provider->checkStatus('FAKE-STATUS-123');

        $this->assertTrue($result->success);
        $this->assertSame(PaymentResult::STATUS_PENDING, $result->status);
        $this->assertSame('FAKE-STATUS-123', $result->providerReference);
    }

    public function test_config_outcome_can_be_set_per_environment(): void
    {
        config(['payment_providers.providers.fake.outcome' => FakePaymentProvider::OUTCOME_FAILED]);
        FakePaymentProvider::clearForcedOutcome();

        $provider = $this->manager()->resolve('fake');
        $result = $provider->initiateFunding($this->instruction(
            PaymentInstruction::OPERATION_LENDER_FUNDING,
            PaymentInstruction::METHOD_PAYMENT_LINK,
            PaymentInstruction::EXECUTION_AUTOMATED,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $result->status);
    }
}
