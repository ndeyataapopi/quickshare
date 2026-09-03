<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\Models\PaymentAuditLog;
use App\Modules\Payments\Providers\FakePaymentProvider;
use App\Modules\Payments\Services\PaymentConfigurationResolver;
use App\Modules\Payments\Services\PaymentExecutionOrchestrator;
use App\Modules\Payments\Services\PaymentProviderStatusService;
use App\Modules\Payments\Services\PaymentReconciliationService;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentPilotReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        FakePaymentProvider::clearForcedOutcome();

        $this->configureFakeProvider();
        $this->resetOperationsToManual();
    }

    protected function configureFakeProvider(): void
    {
        config([
            'payment_providers.default_provider' => 'fake',
            'payment_providers.providers.fake' => [
                'driver' => 'fake',
                'outcome' => 'success',
                'configured' => true,
                'healthy' => true,
                'supported_methods' => [
                    'lender_funding' => ['payment_link', 'debit_order'],
                    'borrower_disbursement' => ['bank_payout', 'wallet_payout'],
                    'borrower_repayment' => ['payment_link', 'debit_order'],
                    'lender_returns' => ['bank_payout', 'wallet_payout'],
                ],
            ],
        ]);
    }

    protected function resetOperationsToManual(): void
    {
        config(['payment_providers.automation_enabled' => true]);

        foreach (PaymentInstruction::operations() as $operation) {
            config(["payment_providers.operations.{$operation}" => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ]]);
        }
    }

    protected function resolver(): PaymentConfigurationResolver
    {
        return app(PaymentConfigurationResolver::class);
    }

    protected function orchestrator(): PaymentExecutionOrchestrator
    {
        return app(PaymentExecutionOrchestrator::class);
    }

    protected function statusService(): PaymentProviderStatusService
    {
        return app(PaymentProviderStatusService::class);
    }

    protected function reconciliationService(): PaymentReconciliationService
    {
        return app(PaymentReconciliationService::class);
    }

    protected function enableOperation(string $operation, string $method, string $provider): void
    {
        config(["payment_providers.operations.{$operation}" => [
            'enabled' => true,
            'method' => $method,
            'mode' => 'automated',
            'provider' => $provider,
        ]]);
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
        $funding = FundingTransaction::create([
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
            'funding_transaction_id' => $funding->id,
            'amount' => $amount,
            'principal_return' => $amount,
            'interest_earned' => 0,
            'funding_percentage' => 100,
            'status' => 'pending',
            'transaction_reference' => 'LRET-'.strtoupper(Str::random(12)),
        ]);
    }

    // ─── Global Emergency Kill Switch ─────────────────────────────────────

    public function test_global_kill_switch_forces_all_operations_to_manual(): void
    {
        config([
            'payment_providers.automation_enabled' => false,
            'payment_providers.operations.lender_funding' => [
                'enabled' => true,
                'method' => 'payment_link',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
            'payment_providers.operations.borrower_disbursement' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
            'payment_providers.operations.borrower_repayment' => [
                'enabled' => true,
                'method' => 'debit_order',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
            'payment_providers.operations.lender_returns' => [
                'enabled' => true,
                'method' => 'bank_payout',
                'mode' => 'automated',
                'provider' => 'fake',
            ],
        ]);

        foreach (PaymentInstruction::operations() as $operation) {
            $config = $this->resolver()->resolve($operation);
            $this->assertTrue($config->isManual(), "Operation {$operation} should be manual when global kill switch is off.");
            $this->assertSame('manual', $config->provider);
        }
    }

    public function test_global_kill_switch_logs_manual_fallback(): void
    {
        config(['payment_providers.automation_enabled' => false]);
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');

        $transaction = $this->createAwaitingDisbursement();
        $this->orchestrator()->executeDisbursement($transaction);

        $this->assertDatabaseHas('payment_audit_logs', [
            'operation' => PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            'event' => 'manual_fallback',
            'transaction_id' => $transaction->id,
        ]);
    }

    // ─── Per-Operation Enablement ──────────────────────────────────────────

    public function test_per_operation_enablement_controls_automation_independently(): void
    {
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');

        $disbursement = $this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);
        $this->assertTrue($disbursement->isAutomated());
        $this->assertSame('bank_payout', $disbursement->method);
        $this->assertSame('fake', $disbursement->provider);

        $funding = $this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);
        $this->assertTrue($funding->isManual());
        $this->assertSame('manual', $funding->provider);
    }

    public function test_disabling_single_operation_keeps_others_automated(): void
    {
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');
        $this->enableOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, 'payment_link', 'fake');
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, 'debit_order', 'fake');
        $this->enableOperation(PaymentInstruction::OPERATION_LENDER_RETURN, 'bank_payout', 'fake');

        config(['payment_providers.operations.borrower_repayment.enabled' => false]);

        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING)->isAutomated());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT)->isAutomated());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_RETURN)->isAutomated());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT)->isManual());
    }

    // ─── Provider Health / Status ───────────────────────────────────────────

    public function test_provider_status_exposes_configured_healthy_and_supported_methods(): void
    {
        $status = $this->statusService()->status('fake');

        $this->assertTrue($status['configured']);
        $this->assertTrue($status['healthy']);
        $this->assertArrayHasKey('lender_funding', $status['supported_methods']);
        $this->assertContains('bank_payout', $status['supported_methods']['borrower_disbursement']);
    }

    public function test_provider_statuses_includes_all_registered_providers(): void
    {
        $statuses = $this->statusService()->providerStatuses();

        $this->assertArrayHasKey('manual', $statuses);
        $this->assertArrayHasKey('fake', $statuses);

        $this->assertTrue($statuses['manual']['configured']);
        $this->assertTrue($statuses['manual']['healthy']);
    }

    public function test_automation_status_reports_global_and_per_operation_state(): void
    {
        config(['payment_providers.automation_enabled' => true]);
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');

        $status = $this->statusService()->automationStatus();

        $this->assertTrue($status['global_automation_enabled']);
        $this->assertTrue($status['operations']['borrower_disbursement']['automation_active']);
        $this->assertFalse($status['operations']['lender_funding']['automation_active']);
    }

    // ─── Audit Logging ────────────────────────────────────────────────────

    public function test_automated_execution_creates_audit_log(): void
    {
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');

        $transaction = $this->createAwaitingDisbursement();
        $this->orchestrator()->executeDisbursement($transaction);

        $this->assertDatabaseHas('payment_audit_logs', [
            'operation' => PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            'event' => 'execution_started',
            'provider' => 'fake',
            'transaction_id' => $transaction->id,
        ]);

        $this->assertDatabaseHas('payment_audit_logs', [
            'operation' => PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            'event' => 'execution_result',
            'status' => PaymentResult::STATUS_COMPLETED,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function test_audit_log_never_records_secrets(): void
    {
        config([
            'payment_providers.providers.fake' => array_merge(
                config('payment_providers.providers.fake'),
                ['api_key' => 'super-secret-api-key-12345']
            ),
        ]);

        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');
        $transaction = $this->createAwaitingDisbursement();
        $this->orchestrator()->executeDisbursement($transaction);

        $logs = PaymentAuditLog::where('transaction_id', $transaction->id)->get();

        foreach ($logs as $log) {
            $json = json_encode($log->metadata ?? []);
            $this->assertStringNotContainsString('super-secret-api-key-12345', $json);
            $this->assertStringNotContainsString('api_key', $json);
        }
    }

    public function test_manual_fallback_is_audited(): void
    {
        config(['payment_providers.automation_enabled' => false]);
        $transaction = $this->createAwaitingDisbursement();
        $this->orchestrator()->executeDisbursement($transaction);

        $this->assertDatabaseHas('payment_audit_logs', [
            'event' => 'manual_fallback',
            'transaction_id' => $transaction->id,
        ]);
    }

    // ─── Reconciliation ─────────────────────────────────────────────────────

    public function test_amount_reconciliation_matches_when_amounts_are_equal(): void
    {
        $transaction = $this->createAwaitingDisbursement(1000.00);

        $result = $this->reconciliationService()->compareAmounts(
            $transaction,
            1000.00,
            1000.00,
            'fake',
            'REF-123'
        );

        $this->assertTrue($result['matched']);
        $this->assertSame(0.0, $result['difference']);

        $this->assertDatabaseHas('payment_audit_logs', [
            'event' => 'amount_reconciled',
            'status' => 'matched',
            'expected_amount' => 1000.00,
            'reported_amount' => 1000.00,
        ]);
    }

    public function test_amount_mismatch_is_logged_but_does_not_modify_allocation(): void
    {
        $transaction = $this->createAwaitingDisbursement(1000.00);
        $originalStatus = $transaction->status;

        $result = $this->reconciliationService()->compareAmounts(
            $transaction,
            1000.00,
            950.00,
            'fake',
            'REF-456'
        );

        $this->assertFalse($result['matched']);
        $this->assertSame(-50.0, $result['difference']);

        $this->assertDatabaseHas('payment_audit_logs', [
            'event' => 'amount_mismatch',
            'status' => 'mismatch',
            'expected_amount' => 1000.00,
            'reported_amount' => 950.00,
        ]);

        $this->assertSame($originalStatus, $transaction->fresh()->status);
    }

    public function test_settlement_record_is_logged_without_modifying_allocation(): void
    {
        $transaction = $this->createAwaitingDisbursement(1000.00);
        $originalStatus = $transaction->status;

        $result = $this->reconciliationService()->recordSettlement(
            $transaction,
            1000.00,
            'fake',
            'SETTLE-001'
        );

        $this->assertTrue($result['matched']);
        $this->assertDatabaseHas('payment_audit_logs', [
            'event' => 'settlement_recorded',
            'status' => 'recorded',
            'provider_reference' => 'SETTLE-001',
        ]);

        $this->assertSame($originalStatus, $transaction->fresh()->status);
    }

    public function test_webhook_reconciliation_logs_match_when_amounts_equal(): void
    {
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, 'payment_link', 'fake');

        $repayment = $this->createPendingRepayment(1000.00);
        $repayment->update(['provider_reference' => 'FAKE-REP-001', 'provider' => 'fake']);

        $payload = [
            'event_type' => 'payment.completed',
            'event_id' => 'EVT-001',
            'provider_reference' => 'FAKE-REP-001',
            'reference' => $repayment->transaction_reference,
            'status' => 'completed',
            'amount' => 1000.00,
            'currency' => 'NAD',
        ];

        $this->postJson('/api/payments/webhooks/fake', $payload)->assertOk();

        $this->assertDatabaseHas('payment_audit_logs', [
            'operation' => PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            'event' => 'amount_reconciled',
            'transaction_id' => $repayment->id,
            'expected_amount' => 1000.00,
            'reported_amount' => 1000.00,
        ]);

        $this->assertSame('paid', $repayment->fresh()->status);
    }

    public function test_webhook_with_mismatched_amount_rejects_and_does_not_modify_allocation(): void
    {
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, 'payment_link', 'fake');

        $repayment = $this->createPendingRepayment(1000.00);
        $repayment->update(['provider_reference' => 'FAKE-REP-001', 'provider' => 'fake']);

        $payload = [
            'event_type' => 'payment.completed',
            'event_id' => 'EVT-002',
            'provider_reference' => 'FAKE-REP-001',
            'reference' => $repayment->transaction_reference,
            'status' => 'completed',
            'amount' => 950.00,
            'currency' => 'NAD',
        ];

        $this->postJson('/api/payments/webhooks/fake', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Webhook amount does not match transaction amount.');

        $this->assertSame('pending_approval', $repayment->fresh()->status);
    }

    // ─── Manual Fallback Permanence ───────────────────────────────────────

    public function test_manual_workflow_remains_available_when_all_automation_disabled(): void
    {
        config(['payment_providers.automation_enabled' => false]);

        foreach (PaymentInstruction::operations() as $operation) {
            config(["payment_providers.operations.{$operation}" => [
                'enabled' => false,
                'method' => 'manual',
                'mode' => 'manual',
                'provider' => 'manual',
            ]]);
        }

        $funding = $this->createFundingTransaction();
        $disbursement = $this->createAwaitingDisbursement();
        $repayment = $this->createPendingRepayment();
        $lenderReturn = $this->createPendingLenderReturn();

        $this->assertSame(PaymentResult::STATUS_MANUAL, $this->orchestrator()->executeFunding($funding)->status);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $this->orchestrator()->executeDisbursement($disbursement)->status);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $this->orchestrator()->executeRepayment($repayment)->status);
        $this->assertSame(PaymentResult::STATUS_MANUAL, $this->orchestrator()->executeLenderReturn($lenderReturn)->status);
    }

    // ─── Rollback / One-at-a-Time Pilot ───────────────────────────────────

    public function test_pilot_can_enable_borrower_disbursement_first_without_others(): void
    {
        config(['payment_providers.automation_enabled' => true]);
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');

        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT)->isAutomated());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING)->isManual());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT)->isManual());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_RETURN)->isManual());
    }

    public function test_rollback_disables_automation_for_a_single_operation(): void
    {
        $this->enableOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, 'bank_payout', 'fake');
        $this->enableOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, 'payment_link', 'fake');

        config(['payment_providers.operations.borrower_disbursement.enabled' => false]);

        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING)->isAutomated());
        $this->assertTrue($this->resolver()->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT)->isManual());
    }
}
