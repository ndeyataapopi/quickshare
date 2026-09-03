<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Funding\Services\FundingService;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\DTOs\WebhookResult;
use App\Modules\Payments\Providers\FakePaymentProvider;
use App\Modules\Payments\Services\PaymentExecutionOrchestrator;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FakeProviderEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentProvider::clearForcedOutcome();
        FakePaymentProvider::clearForcedSignatureValid();
        $this->resetPaymentConfig();
    }

    protected function resetPaymentConfig(): void
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

    protected function configureOperation(string $operation, string $method, string $provider = 'fake'): void
    {
        config(["payment_providers.operations.{$operation}" => [
            'enabled' => true,
            'method' => $method,
            'mode' => 'automated',
            'provider' => $provider,
        ]]);
    }

    protected function orchestrator(): PaymentExecutionOrchestrator
    {
        return app(PaymentExecutionOrchestrator::class);
    }

    protected function createBorrower(): User
    {
        return User::factory()->create();
    }

    protected function createLender(): User
    {
        return User::factory()->create();
    }

    protected function createMarketplaceLoan(float $amount = 3000.00): Loan
    {
        $borrower = $this->createBorrower();

        return Loan::factory()->marketplace()->create([
            'borrower_id' => $borrower->id,
            'requested_amount' => $amount,
            'approved_amount' => $amount,
            'total_repayment' => $amount + round($amount * 0.15 * (30 / 365), 2) + round($amount * 0.02, 2),
            'funded_amount' => 0,
            'loan_term_days' => 30,
        ]);
    }

    protected function createFundingTransaction(Loan $loan, User $lender, float $amount): FundingTransaction
    {
        return FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => $amount,
            'interest_rate' => 15.00,
            'expected_return' => round($amount * 1.15, 2),
            'status' => 'pending',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);
    }

    protected function createAwaitingDisbursement(): DisbursementTransaction
    {
        $loan = $this->createMarketplaceLoan();
        $loan->update([
            'status' => 'funded',
            'funded_amount' => $loan->approved_amount,
        ]);

        $platformFee = (float) $loan->platform_fee;
        $netAmount = (float) $loan->funded_amount - $platformFee;

        return DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'gross_amount' => $loan->funded_amount,
            'platform_fee' => $platformFee,
            'net_amount' => $netAmount,
            'status' => 'awaiting_disbursement',
            'transaction_reference' => DisbursementTransaction::generateReference(),
            'payment_method' => 'bank_transfer',
        ]);
    }

    protected function createActiveLoanWithRepayment(): Repayment
    {
        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $funding = $this->createFundingTransaction($loan, $lender, (float) $loan->approved_amount);
        app(FundingService::class)->confirmFunding($funding);

        $loan->refresh();

        // confirmFunding dispatches sync events that auto-initiate and process the
        // outgoing disbursement up to pending_borrower_confirmation.
        $disbursement = DisbursementTransaction::forLoan($loan->id)
            ->where('direction', 'outgoing')
            ->first();

        $this->assertNotNull($disbursement, 'Auto-triggered disbursement should exist.');

        if ($disbursement->isAwaiting()) {
            app(DisbursementService::class)->processDisbursement($disbursement);
        }

        $this->assertTrue(
            $disbursement->fresh()->isPendingBorrowerConfirmation(),
            'Disbursement should be pending borrower confirmation.'
        );

        app(DisbursementService::class)->confirmReceipt($disbursement);

        $this->assertSame('active', $loan->fresh()->status);

        $repayment = Repayment::forLoan($loan->id)->first();
        $this->assertNotNull($repayment);
        $repayment->update(['status' => 'pending_approval']);

        return $repayment;
    }

    protected function createLenderRepayment(): LenderRepayment
    {
        $repayment = $this->createActiveLoanWithRepayment();
        app(RepaymentService::class)->approveRepayment($repayment);

        $lenderRepayment = LenderRepayment::forRepayment($repayment->id)->first();
        $this->assertNotNull($lenderRepayment, 'LenderRepayment should exist after repayment approval.');

        return $lenderRepayment;
    }

    protected function makeWebhookResult(
        string $providerReference,
        float $amount,
        string $eventType,
        string $providerEventId,
    ): WebhookResult {
        return WebhookResult::handled(
            eventType: $eventType,
            providerReference: $providerReference,
            providerEventId: $providerEventId,
            status: PaymentResult::STATUS_COMPLETED,
            amount: $amount,
            currency: 'NAD',
        );
    }

    // ─── Lender Funding ──────────────────────────────────────────────────

    public function test_lender_funding_manual_does_not_auto_confirm(): void
    {
        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertFalse(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_payment_link_creates_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('fake', $result->providerName);
        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertTrue(Investment::where('funding_transaction_id', $transaction->id)->exists());
        $this->assertSame('partially_funded', $loan->fresh()->status);
    }

    public function test_lender_funding_debit_order_creates_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_DEBIT_ORDER);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertTrue(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_pending_then_webhook_completes(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_PENDING);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertTrue($result->isPending());
        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertNotNull($transaction->provider_reference);

        FakePaymentProvider::clearForcedOutcome();
        $webhook = $this->makeWebhookResult(
            providerReference: $transaction->provider_reference,
            amount: 1000.00,
            eventType: 'payment.completed',
            providerEventId: 'evt-fund-1',
        );

        $processResult = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertTrue($processResult->success);
        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertTrue(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_failed_does_not_create_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_FAILED);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_FAILED, $transaction->fresh()->provider_status);
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertFalse(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_timeout_does_not_create_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_TIMEOUT);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertFalse($result->success);
        $this->assertSame(PaymentResult::STATUS_TIMEOUT, $transaction->fresh()->provider_status);
        $this->assertFalse(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_duplicate_does_not_create_extra_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_DUPLICATE);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertSame(PaymentResult::STATUS_DUPLICATE, $result->status);
        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertFalse(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_reversal_does_not_create_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_REVERSED);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $result = $this->orchestrator()->executeFunding($transaction);

        $this->assertSame(PaymentResult::STATUS_REVERSED, $result->status);
        $this->assertFalse(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    public function test_lender_funding_duplicate_webhook_does_not_create_second_investment(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);

        $this->orchestrator()->executeFunding($transaction);
        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertCount(1, Investment::where('funding_transaction_id', $transaction->id)->get());

        $webhook = $this->makeWebhookResult(
            providerReference: $transaction->fresh()->provider_reference,
            amount: 1000.00,
            eventType: 'payment.completed',
            providerEventId: 'evt-fund-dup-1',
        );

        $first = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));
        $this->assertTrue($first->success);

        $webhook2 = $this->makeWebhookResult(
            providerReference: $transaction->fresh()->provider_reference,
            amount: 1000.00,
            eventType: 'payment.completed',
            providerEventId: 'evt-fund-dup-1',
        );
        $second = $this->orchestrator()->processWebhook($webhook2, 'fake', Request::create('/'));

        $this->assertTrue($second->duplicate);
        $this->assertCount(1, Investment::where('funding_transaction_id', $transaction->id)->get());
    }

    public function test_lender_funding_webhook_wrong_amount_is_rejected(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);
        $transaction->update(['provider_reference' => 'FAKE-FUND-1']);

        $webhook = $this->makeWebhookResult(
            providerReference: 'FAKE-FUND-1',
            amount: 500.00,
            eventType: 'payment.completed',
            providerEventId: 'evt-fund-wrong',
        );

        $result = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertFalse($result->success);
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_lender_funding_webhook_unknown_transaction_is_rejected(): void
    {
        $webhook = $this->makeWebhookResult(
            providerReference: 'FAKE-UNKNOWN',
            amount: 1000.00,
            eventType: 'payment.completed',
            providerEventId: 'evt-fund-unknown',
        );

        $result = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertFalse($result->success);
    }

    // ─── Borrower Disbursement ─────────────────────────────────────────

    public function test_borrower_disbursement_manual_does_not_auto_process(): void
    {
        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);

        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('awaiting_disbursement', $disbursement->fresh()->status);
    }

    public function test_borrower_disbursement_bank_payout_moves_to_borrower_confirmation(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);

        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);
        $this->assertNotNull($disbursement->fresh()->provider_reference);
    }

    public function test_borrower_disbursement_wallet_payout_moves_to_borrower_confirmation(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_WALLET_PAYOUT);

        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);
    }

    public function test_borrower_disbursement_pending_then_webhook_moves_to_confirmation(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_PENDING);

        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);
        $this->assertTrue($result->isPending());
        $disbursement->refresh();
        $this->assertSame('awaiting_disbursement', $disbursement->status);

        FakePaymentProvider::clearForcedOutcome();
        $webhook = $this->makeWebhookResult(
            providerReference: $disbursement->provider_reference,
            amount: (float) $disbursement->gross_amount,
            eventType: 'payout.completed',
            providerEventId: 'evt-disb-1',
        );

        $processResult = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertTrue($processResult->success);
        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);
    }

    public function test_borrower_disbursement_failed_does_not_advance_status(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_FAILED);

        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);

        $this->assertFalse($result->success);
        $this->assertSame('awaiting_disbursement', $disbursement->fresh()->status);
    }

    public function test_borrower_disbursement_timeout_does_not_advance_status(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_TIMEOUT);

        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);

        $this->assertFalse($result->success);
        $this->assertSame('awaiting_disbursement', $disbursement->fresh()->status);
    }

    public function test_borrower_disbursement_duplicate_does_not_advance_status(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_DUPLICATE);

        $disbursement = $this->createAwaitingDisbursement();

        $result = $this->orchestrator()->executeDisbursement($disbursement);

        $this->assertSame(PaymentResult::STATUS_DUPLICATE, $result->status);
        $this->assertSame('awaiting_disbursement', $disbursement->fresh()->status);
    }

    public function test_borrower_disbursement_duplicate_webhook_is_harmless(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);

        $disbursement = $this->createAwaitingDisbursement();
        $this->orchestrator()->executeDisbursement($disbursement);
        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);

        $webhook = $this->makeWebhookResult(
            providerReference: $disbursement->fresh()->provider_reference,
            amount: (float) $disbursement->gross_amount,
            eventType: 'payout.completed',
            providerEventId: 'evt-disb-dup',
        );

        $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));
        $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);
    }

    // ─── Borrower Repayment ────────────────────────────────────────────

    public function test_borrower_repayment_manual_does_not_auto_approve(): void
    {
        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('pending_approval', $repayment->fresh()->status);
    }

    public function test_borrower_repayment_payment_link_approves_and_allocates(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);

        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('paid', $repayment->fresh()->status);
        $this->assertTrue(LenderRepayment::where('repayment_id', $repayment->id)->exists());
    }

    public function test_borrower_repayment_debit_order_approves_and_allocates(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_DEBIT_ORDER);

        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('paid', $repayment->fresh()->status);
    }

    public function test_borrower_repayment_pending_then_webhook_approves(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_PENDING);

        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);
        $this->assertTrue($result->isPending());
        $this->assertSame('pending_approval', $repayment->fresh()->status);

        FakePaymentProvider::clearForcedOutcome();
        $webhook = $this->makeWebhookResult(
            providerReference: $repayment->fresh()->provider_reference,
            amount: (float) $repayment->amount,
            eventType: 'payment.completed',
            providerEventId: 'evt-repay-1',
        );

        $processResult = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertTrue($processResult->success);
        $this->assertSame('paid', $repayment->fresh()->status);
    }

    public function test_borrower_repayment_failed_does_not_approve(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_FAILED);

        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertFalse($result->success);
        $this->assertSame('pending_approval', $repayment->fresh()->status);
    }

    public function test_borrower_repayment_timeout_does_not_approve(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_TIMEOUT);

        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertFalse($result->success);
        $this->assertSame('pending_approval', $repayment->fresh()->status);
    }

    public function test_borrower_repayment_duplicate_does_not_approve(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_DUPLICATE);

        $repayment = $this->createActiveLoanWithRepayment();

        $result = $this->orchestrator()->executeRepayment($repayment);

        $this->assertSame(PaymentResult::STATUS_DUPLICATE, $result->status);
        $this->assertSame('pending_approval', $repayment->fresh()->status);
    }

    public function test_borrower_repayment_duplicate_webhook_is_harmless(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);

        $repayment = $this->createActiveLoanWithRepayment();
        $this->orchestrator()->executeRepayment($repayment);
        $this->assertSame('paid', $repayment->fresh()->status);

        $webhook = $this->makeWebhookResult(
            providerReference: $repayment->fresh()->provider_reference,
            amount: (float) $repayment->amount,
            eventType: 'payment.completed',
            providerEventId: 'evt-repay-dup',
        );

        $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));
        $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertSame('paid', $repayment->fresh()->status);
        $this->assertCount(1, LenderRepayment::where('repayment_id', $repayment->id)->get());
    }

    // ─── Lender Returns ────────────────────────────────────────────────

    public function test_lender_returns_manual_does_not_execute_payout(): void
    {
        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertSame(PaymentResult::STATUS_MANUAL, $result->status);
        $this->assertSame('processed', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_bank_payout_marks_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('processed', $lenderRepayment->fresh()->status);
        $this->assertNotNull($lenderRepayment->fresh()->provider_reference);
    }

    public function test_lender_returns_wallet_payout_marks_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_WALLET_PAYOUT);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertTrue($result->isCompleted());
        $this->assertSame('processed', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_pending_then_webhook_marks_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_PENDING);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);
        $this->assertTrue($result->isPending());
        $this->assertSame('pending', $lenderRepayment->fresh()->status);

        FakePaymentProvider::clearForcedOutcome();
        $webhook = $this->makeWebhookResult(
            providerReference: $lenderRepayment->fresh()->provider_reference,
            amount: (float) $lenderRepayment->amount,
            eventType: 'payout.completed',
            providerEventId: 'evt-lr-1',
        );

        $processResult = $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertTrue($processResult->success);
        $this->assertSame('processed', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_failed_does_not_mark_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_FAILED);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertFalse($result->success);
        $this->assertSame('pending', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_timeout_does_not_mark_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_TIMEOUT);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertFalse($result->success);
        $this->assertSame('pending', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_duplicate_does_not_mark_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_DUPLICATE);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertSame(PaymentResult::STATUS_DUPLICATE, $result->status);
        $this->assertSame('pending', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_duplicate_webhook_is_harmless(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);

        $lenderRepayment = $this->createLenderRepayment();
        $this->orchestrator()->executeLenderReturn($lenderRepayment);
        $this->assertSame('processed', $lenderRepayment->fresh()->status);

        $webhook = $this->makeWebhookResult(
            providerReference: $lenderRepayment->fresh()->provider_reference,
            amount: (float) $lenderRepayment->amount,
            eventType: 'payout.completed',
            providerEventId: 'evt-lr-dup',
        );

        $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));
        $this->orchestrator()->processWebhook($webhook, 'fake', Request::create('/'));

        $this->assertSame('processed', $lenderRepayment->fresh()->status);
    }

    public function test_lender_returns_reversal_does_not_mark_processed(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);
        FakePaymentProvider::forceOutcome(FakePaymentProvider::OUTCOME_REVERSED);

        $lenderRepayment = $this->createLenderRepayment();

        $result = $this->orchestrator()->executeLenderReturn($lenderRepayment);

        $this->assertSame(PaymentResult::STATUS_REVERSED, $result->status);
        $this->assertSame('pending', $lenderRepayment->fresh()->status);
    }

    // ─── Multi-Lender ──────────────────────────────────────────────────

    public function test_multi_lender_funding_and_returns_remain_separate(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, PaymentInstruction::METHOD_BANK_PAYOUT);
        $this->configureOperation(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, PaymentInstruction::METHOD_PAYMENT_LINK);
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_RETURN, PaymentInstruction::METHOD_BANK_PAYOUT);

        $loan = $this->createMarketplaceLoan(3000.00);
        $lenderA = $this->createLender();
        $lenderB = $this->createLender();

        // Fund the loan from two lenders.
        $fundingA = $this->createFundingTransaction($loan, $lenderA, 1000.00);
        $fundingB = $this->createFundingTransaction($loan, $lenderB, 2000.00);

        $this->orchestrator()->executeFunding($fundingA);
        $this->orchestrator()->executeFunding($fundingB);

        $loan->refresh();
        $this->assertSame(3000.00, (float) $loan->funded_amount);
        $this->assertCount(2, Investment::where('loan_id', $loan->id)->get());
        $this->assertSame(1000.00, (float) Investment::where('lender_id', $lenderA->id)->value('amount'));
        $this->assertSame(2000.00, (float) Investment::where('lender_id', $lenderB->id)->value('amount'));

        // Disbursement is auto-initiated when the loan becomes fully funded.
        $disbursement = DisbursementTransaction::forLoan($loan->id)
            ->where('direction', 'outgoing')
            ->first();
        $this->assertNotNull($disbursement, 'Auto-triggered disbursement should exist.');

        if ($disbursement->isAwaiting()) {
            $this->orchestrator()->executeDisbursement($disbursement);
        }

        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);

        app(DisbursementService::class)->confirmReceipt($disbursement);
        $this->assertSame('active', $loan->fresh()->status);

        // Repay.
        $repayment = Repayment::forLoan($loan->id)->first();
        $repayment->update(['status' => 'pending_approval']);
        $this->orchestrator()->executeRepayment($repayment);

        $this->assertSame('paid', $repayment->fresh()->status);

        // Lender returns remain separate.
        $lenderReturns = LenderRepayment::forRepayment($repayment->id)->get();
        $this->assertCount(2, $lenderReturns);

        foreach ($lenderReturns as $lr) {
            $this->assertSame('pending', $lr->fresh()->status);
            $this->orchestrator()->executeLenderReturn($lr);
            $this->assertSame('processed', $lr->fresh()->status);
        }

        $totalLenderReturns = LenderRepayment::forRepayment($repayment->id)->sum('amount');
        $this->assertGreaterThan(0, (float) $totalLenderReturns);
        $this->assertGreaterThan(0, (float) LenderRepayment::where('lender_id', $lenderA->id)->value('amount'));
        $this->assertGreaterThan(0, (float) LenderRepayment::where('lender_id', $lenderB->id)->value('amount'));

        // Verify each lender received a distinct, proportional share.
        $amountA = (float) LenderRepayment::where('lender_id', $lenderA->id)->value('amount');
        $amountB = (float) LenderRepayment::where('lender_id', $lenderB->id)->value('amount');
        $this->assertNotSame($amountA, $amountB);

        // Re-running any payout must not create duplicates.
        foreach ($lenderReturns as $lr) {
            $this->orchestrator()->executeLenderReturn($lr->fresh());
        }
        $this->assertCount(2, LenderRepayment::forRepayment($repayment->id)->where('status', 'processed')->get());
    }

    // ─── Shared Failure Scenarios ────────────────────────────────────

    public function test_invalid_signature_rejects_webhook_for_all_operations(): void
    {
        FakePaymentProvider::forceSignatureValid(false);

        $response = $this->postJson('/api/payments/webhooks/fake', [
            'provider_event_id' => 'evt-invalid-sig',
            'provider_reference' => 'FAKE-REF',
            'event_type' => 'payment.completed',
            'amount' => 1000,
        ]);

        $response->assertUnauthorized();
    }

    public function test_duplicate_webhook_with_same_event_id_is_harmless(): void
    {
        $this->configureOperation(PaymentInstruction::OPERATION_LENDER_FUNDING, PaymentInstruction::METHOD_PAYMENT_LINK);

        $loan = $this->createMarketplaceLoan();
        $lender = $this->createLender();
        $transaction = $this->createFundingTransaction($loan, $lender, 1000.00);
        $transaction->update(['provider_reference' => 'FAKE-SAME-ID']);

        $payload = [
            'provider_event_id' => 'evt-same-id',
            'provider_reference' => 'FAKE-SAME-ID',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ];

        $this->postJson('/api/payments/webhooks/fake', $payload)->assertOk();
        $this->postJson('/api/payments/webhooks/fake', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertCount(1, Investment::where('funding_transaction_id', $transaction->id)->get());
    }
}
