<?php

namespace Tests\Feature\Payments;

use App\Modules\Payments\DTOs\OperationConfiguration;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\Exceptions\InvalidPaymentConfigurationException;
use App\Modules\Payments\Services\PaymentConfigurationResolver;
use Tests\TestCase;

class PaymentConfigurationResolverTest extends TestCase
{
    protected function resolver(): PaymentConfigurationResolver
    {
        return app(PaymentConfigurationResolver::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Enable automation globally so per-operation configuration is meaningful.
        config(['payment_providers.automation_enabled' => true]);

        // Reset to the default manual state for each test.
        config([
            'payment_providers.operations.lender_funding' => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
            'payment_providers.operations.borrower_repayment' => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
            'payment_providers.operations.lender_returns' => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
        ]);
    }

    // ─── Default Manual Behaviour ──────────────────────────────────────

    public function test_lender_funding_defaults_to_manual(): void
    {
        $config = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);

        $this->assertInstanceOf(OperationConfiguration::class, $config);
        $this->assertSame(PaymentInstruction::OPERATION_LENDER_FUNDING, $config->operation);
        $this->assertSame('manual', $config->method);
        $this->assertSame('manual', $config->mode);
        $this->assertSame('manual', $config->provider);
        $this->assertTrue($config->isManual());
        $this->assertFalse($config->isAutomated());
    }

    public function test_borrower_disbursement_defaults_to_manual(): void
    {
        $config = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);

        $this->assertSame('manual', $config->method);
        $this->assertSame('manual', $config->mode);
        $this->assertSame('manual', $config->provider);
        $this->assertTrue($config->isManual());
    }

    public function test_borrower_repayment_defaults_to_manual(): void
    {
        $config = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT);

        $this->assertSame('manual', $config->method);
        $this->assertSame('manual', $config->mode);
        $this->assertSame('manual', $config->provider);
        $this->assertTrue($config->isManual());
    }

    public function test_lender_returns_defaults_to_manual(): void
    {
        $config = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_RETURN);

        $this->assertSame('manual', $config->method);
        $this->assertSame('manual', $config->mode);
        $this->assertSame('manual', $config->provider);
        $this->assertTrue($config->isManual());
    }

    public function test_all_operations_default_to_manual(): void
    {
        $all = $this->resolver()->all();

        $this->assertCount(4, $all);

        foreach ($all as $operation => $config) {
            $this->assertSame('manual', $config->method);
            $this->assertSame('manual', $config->mode);
            $this->assertSame('manual', $config->provider);
            $this->assertTrue($config->isManual());
        }
    }

    // ─── Independent Per-Operation Configuration ─────────────────────────

    public function test_each_operation_can_select_independent_method(): void
    {
        config([
            'payment_providers.operations.lender_funding' => [
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
            'payment_providers.operations.borrower_repayment' => [
                'enabled' => true,
                'method' => 'payment_link',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
            'payment_providers.operations.lender_returns' => [
                'enabled' => true,
                'method' => 'wallet_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
        ]);

        $lenderFunding = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
        $this->assertSame('manual', $lenderFunding->method);
        $this->assertSame('manual', $lenderFunding->mode);
        $this->assertSame('manual', $lenderFunding->provider);

        $disbursement = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
        $this->assertSame('bank_payout', $disbursement->method);
        $this->assertSame('automated', $disbursement->mode);
        $this->assertSame('fake', $disbursement->provider);
        $this->assertTrue($disbursement->isAutomated());

        $repayment = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT);
        $this->assertSame('payment_link', $repayment->method);
        $this->assertSame('automated', $repayment->mode);
        $this->assertSame('fake', $repayment->provider);

        $returns = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_RETURN);
        $this->assertSame('wallet_payout', $returns->method);
        $this->assertSame('automated', $returns->mode);
        $this->assertSame('fake', $returns->provider);
    }

    public function test_automated_and_manual_filters_work(): void
    {
        config([
            'payment_providers.operations.lender_funding' => [
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
            'payment_providers.operations.borrower_repayment' => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ],
            'payment_providers.operations.lender_returns' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
        ]);

        $manual = $this->resolver()->manual();
        $automated = $this->resolver()->automated();

        $this->assertCount(2, $manual);
        $this->assertCount(2, $automated);
        $this->assertArrayHasKey(PaymentInstruction::OPERATION_LENDER_FUNDING, $manual);
        $this->assertArrayHasKey(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, $automated);
    }

    // ─── Validation ─────────────────────────────────────────────────────

    public function test_unknown_operation_is_rejected(): void
    {
        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Unknown payment operation: refund');

        $this->resolver()->resolve('refund');
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        config([
            'payment_providers.operations.lender_funding' => [
                'enabled' => true,
                'method' => 'crypto',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
        ]);

        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Unknown payment method [crypto] for operation [lender_funding]');

        $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
    }

    public function test_unknown_execution_mode_is_rejected(): void
    {
        config([
            'payment_providers.operations.lender_funding' => [
                'enabled' => true,
                'method' => 'manual',
                'mode' => 'scheduled',
                'provider' => 'manual',
            ],
        ]);

        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Unknown execution mode [scheduled] for operation [lender_funding]');

        $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
    }

    public function test_automated_method_without_provider_is_rejected(): void
    {
        config([
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => '',
            ],
        ]);

        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Operation [borrower_disbursement] is automated but no provider is configured');

        $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
    }

    public function test_automated_method_with_manual_provider_is_rejected(): void
    {
        config([
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'manual',
            ],
        ]);

        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Operation [borrower_disbursement] is automated but no provider is configured');

        $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
    }

    public function test_manual_mode_with_non_manual_provider_is_rejected(): void
    {
        config([
            'payment_providers.operations.lender_funding' => [
                'enabled' => true,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'fake',
            ],
        ]);

        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Operation [lender_funding] is manual but provider is not manual');

        $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
    }

    public function test_unregistered_provider_is_rejected(): void
    {
        config([
            'payment_providers.operations.borrower_repayment' => [
                'enabled' => true,
                'method' => 'payment_link',
                'mode' => 'automated',
                'provider' => 'collexia',
            ],
        ]);

        $this->expectException(InvalidPaymentConfigurationException::class);
        $this->expectExceptionMessage('Provider [collexia] for operation [borrower_repayment] is not registered or not configured');

        $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT);
    }

    public function test_provider_must_support_selected_method(): void
    {
        config([
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
        ]);

        // Fake provider supports bank_payout, so this should succeed.
        $config = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
        $this->assertSame('bank_payout', $config->method);
        $this->assertSame('fake', $config->provider);
    }

    public function test_operation_configuration_can_be_exported_to_array(): void
    {
        $config = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);

        $this->assertSame([
            'operation' => PaymentInstruction::OPERATION_LENDER_FUNDING,
            'method' => 'manual',
            'mode' => 'manual',
            'provider' => 'manual',
        ], $config->toArray());
    }
}
