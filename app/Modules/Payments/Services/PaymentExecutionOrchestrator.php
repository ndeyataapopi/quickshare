<?php

namespace App\Modules\Payments\Services;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Services\FundingService;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Services\DisbursementService;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\DTOs\WebhookProcessResult;
use App\Modules\Payments\DTOs\WebhookResult;
use App\Modules\Payments\Models\PaymentAuditLog;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentExecutionOrchestrator
{
    public function __construct(
        protected PaymentConfigurationResolver $configurationResolver,
        protected PaymentExecutionService $executionService,
        protected PaymentWebhookService $webhookService,
        protected PaymentReconciliationService $reconciliationService,
        protected FundingService $fundingService,
        protected DisbursementService $disbursementService,
        protected RepaymentService $repaymentService,
    ) {}

    /**
     * Execute a lender funding payment.
     *
     * On success: confirms the funding and creates an Investment.
     */
    public function executeFunding(FundingTransaction $transaction): PaymentResult
    {
        $config = $this->configurationResolver->resolve(PaymentInstruction::OPERATION_LENDER_FUNDING);

        if ($config->isManual()) {
            $this->logManualFallback(PaymentInstruction::OPERATION_LENDER_FUNDING, $transaction);

            return $this->manualResult(PaymentInstruction::OPERATION_LENDER_FUNDING, $transaction->transaction_reference);
        }

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_LENDER_FUNDING,
            paymentMethod: $config->method,
            executionMode: $config->mode,
            amount: (float) $transaction->amount,
            reference: $transaction->transaction_reference,
            currency: config('loan.general.currency', 'NAD'),
            loanId: $transaction->loan_id,
            userId: $transaction->lender_id,
            provider: $config->provider,
            description: "Automated funding for transaction {$transaction->transaction_reference}",
        );

        $this->logOperationStart($instruction, $transaction);

        $result = $this->executionService->execute($instruction);
        $this->storeProviderResult($transaction, $result);
        $this->logOperationResult($instruction, $transaction, $result);

        if ($result->isCompleted() && $transaction->isPending()) {
            DB::transaction(function () use ($transaction) {
                $this->fundingService->confirmFunding($transaction, null, 'Automated via payment provider.');
            });
            $this->logStatusChange($instruction, $transaction, 'confirmed', 'Funding confirmed from automated execution.');
        }

        return $result;
    }

    /**
     * Execute a borrower disbursement payout.
     *
     * On success: marks the outgoing disbursement as processed (pending borrower confirmation).
     */
    public function executeDisbursement(DisbursementTransaction $transaction): PaymentResult
    {
        $config = $this->configurationResolver->resolve(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT);

        if ($config->isManual()) {
            $this->logManualFallback(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, $transaction);

            return $this->manualResult(PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT, $transaction->transaction_reference);
        }

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            paymentMethod: $config->method,
            executionMode: $config->mode,
            amount: (float) $transaction->gross_amount,
            reference: $transaction->transaction_reference,
            currency: config('loan.general.currency', 'NAD'),
            loanId: $transaction->loan_id,
            provider: $config->provider,
            description: "Automated disbursement for transaction {$transaction->transaction_reference}",
        );

        $this->logOperationStart($instruction, $transaction);

        $result = $this->executionService->execute($instruction);
        $this->storeProviderResult($transaction, $result);
        $this->logOperationResult($instruction, $transaction, $result);

        if ($result->isCompleted() && $transaction->isAwaiting()) {
            DB::transaction(function () use ($transaction, $result) {
                $this->disbursementService->processDisbursement($transaction, [
                    'payment_method' => $result->metadata['payment_method'] ?? 'bank_transfer',
                    'external_reference' => $result->providerReference,
                    'payment_proof_path' => $result->metadata['payment_proof_path'] ?? null,
                ]);
            });
            $this->logStatusChange($instruction, $transaction, 'pending_borrower_confirmation', 'Disbursement processed from automated execution.');
        }

        return $result;
    }

    /**
     * Execute a borrower repayment collection.
     *
     * On success: approves the repayment and distributes to lenders.
     */
    public function executeRepayment(Repayment $repayment): PaymentResult
    {
        $config = $this->configurationResolver->resolve(PaymentInstruction::OPERATION_BORROWER_REPAYMENT);

        if ($config->isManual()) {
            $this->logManualFallback(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, $repayment);

            return $this->manualResult(PaymentInstruction::OPERATION_BORROWER_REPAYMENT, $repayment->transaction_reference);
        }

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            paymentMethod: $config->method,
            executionMode: $config->mode,
            amount: (float) $repayment->amount,
            reference: $repayment->transaction_reference,
            currency: config('loan.general.currency', 'NAD'),
            loanId: $repayment->loan_id,
            userId: $repayment->borrower_id,
            provider: $config->provider,
            description: "Automated repayment collection for repayment {$repayment->transaction_reference}",
        );

        $this->logOperationStart($instruction, $repayment);

        $result = $this->executionService->execute($instruction);
        $this->storeProviderResult($repayment, $result);
        $this->logOperationResult($instruction, $repayment, $result);

        if ($result->isCompleted() && $repayment->isPendingApproval()) {
            DB::transaction(function () use ($repayment) {
                $this->repaymentService->approveRepayment($repayment, null);
            });
            $this->logStatusChange($instruction, $repayment, 'paid', 'Repayment approved from automated execution.');
        }

        return $result;
    }

    /**
     * Execute a lender return payout.
     *
     * On success: marks the LenderRepayment as processed.
     */
    public function executeLenderReturn(LenderRepayment $lenderRepayment): PaymentResult
    {
        $config = $this->configurationResolver->resolve(PaymentInstruction::OPERATION_LENDER_RETURN);

        if ($config->isManual()) {
            $this->logManualFallback(PaymentInstruction::OPERATION_LENDER_RETURN, $lenderRepayment);

            return $this->manualResult(PaymentInstruction::OPERATION_LENDER_RETURN, $lenderRepayment->transaction_reference ?? (string) $lenderRepayment->id);
        }

        $instruction = new PaymentInstruction(
            operation: PaymentInstruction::OPERATION_LENDER_RETURN,
            paymentMethod: $config->method,
            executionMode: $config->mode,
            amount: (float) $lenderRepayment->amount,
            reference: $lenderRepayment->transaction_reference ?? (string) $lenderRepayment->id,
            currency: config('loan.general.currency', 'NAD'),
            loanId: $lenderRepayment->repayment->loan_id ?? null,
            userId: $lenderRepayment->lender_id,
            provider: $config->provider,
            description: "Automated lender return for repayment {$lenderRepayment->repayment_id}",
        );

        $this->logOperationStart($instruction, $lenderRepayment);

        $result = $this->executionService->execute($instruction);
        $this->storeProviderResult($lenderRepayment, $result);
        $this->logOperationResult($instruction, $lenderRepayment, $result);

        if ($result->isCompleted() && $lenderRepayment->isPending()) {
            DB::transaction(function () use ($lenderRepayment, $result) {
                $lenderRepayment->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'provider_reference' => $result->providerReference,
                ]);
            });
            $this->logStatusChange($instruction, $lenderRepayment, 'processed', 'Lender return processed from automated execution.');
        }

        return $result;
    }

    /**
     * Process a verified provider webhook and, if appropriate, drive the
     * corresponding business action exactly once.
     */
    public function processWebhook(WebhookResult $result, string $provider, Request $request): WebhookProcessResult
    {
        $processResult = $this->webhookService->process($result, $provider, $request);

        if (! $processResult->success || $processResult->duplicate || ! $processResult->event) {
            return $processResult;
        }

        $event = $processResult->event;

        if ($event->status !== PaymentWebhookEvent::STATUS_PROCESSED || ! $event->transaction_type || ! $event->transaction_id) {
            return $processResult;
        }

        $transaction = $this->findTransaction($event->transaction_type, $event->transaction_id);

        if ($transaction) {
            $this->reconcileWebhookAmount($transaction, $result, $provider);
        }

        // A duplicate webhook event can never trigger a business action twice
        // because PaymentWebhookService already short-circuits duplicates.
        $this->applyBusinessActionFromWebhook($event);

        return $processResult;
    }

    protected function applyBusinessActionFromWebhook(PaymentWebhookEvent $event): void
    {
        DB::transaction(function () use ($event) {
            match ($event->transaction_type) {
                'funding_transaction' => $this->applyFundingWebhook($event),
                'disbursement_transaction' => $this->applyDisbursementWebhook($event),
                'repayment' => $this->applyRepaymentWebhook($event),
                'lender_repayment' => $this->applyLenderReturnWebhook($event),
                default => null,
            };
        });
    }

    protected function applyFundingWebhook(PaymentWebhookEvent $event): void
    {
        $transaction = FundingTransaction::find($event->transaction_id);

        if (! $transaction || ! $transaction->isPending()) {
            return;
        }

        $this->fundingService->confirmFunding($transaction, null, 'Automated via payment provider webhook.');

        Log::info('Funding confirmed from webhook', [
            'funding_transaction_id' => $transaction->id,
            'webhook_event_id' => $event->id,
        ]);
    }

    protected function applyDisbursementWebhook(PaymentWebhookEvent $event): void
    {
        $transaction = DisbursementTransaction::find($event->transaction_id);

        if (! $transaction || ! $transaction->isAwaiting()) {
            return;
        }

        $this->disbursementService->processDisbursement($transaction, [
            'payment_method' => $event->payload['payment_method'] ?? 'bank_transfer',
            'external_reference' => $event->provider_reference,
        ]);

        Log::info('Disbursement processed from webhook', [
            'disbursement_transaction_id' => $transaction->id,
            'webhook_event_id' => $event->id,
        ]);
    }

    protected function applyRepaymentWebhook(PaymentWebhookEvent $event): void
    {
        $repayment = Repayment::find($event->transaction_id);

        if (! $repayment || ! $repayment->isPendingApproval()) {
            return;
        }

        $this->repaymentService->approveRepayment($repayment, null);

        Log::info('Repayment approved from webhook', [
            'repayment_id' => $repayment->id,
            'webhook_event_id' => $event->id,
        ]);
    }

    protected function applyLenderReturnWebhook(PaymentWebhookEvent $event): void
    {
        $lenderRepayment = LenderRepayment::find($event->transaction_id);

        if (! $lenderRepayment || ! $lenderRepayment->isPending()) {
            return;
        }

        $lenderRepayment->update([
            'status' => 'processed',
            'processed_at' => now(),
            'provider_reference' => $event->provider_reference,
        ]);

        Log::info('Lender return processed from webhook', [
            'lender_repayment_id' => $lenderRepayment->id,
            'webhook_event_id' => $event->id,
        ]);
    }

    /**
     * Store provider execution metadata on the business record.
     */
    protected function storeProviderResult(Model $model, PaymentResult $result): void
    {
        $data = [
            'execution_mode' => $result->metadata['execution_mode'] ?? 'automated',
            'provider' => $result->providerName,
            'provider_reference' => $result->providerReference,
            'provider_status' => $result->status,
            'provider_metadata' => array_merge($result->metadata ?? [], [
                'message' => $result->message,
            ]),
            'provider_error_code' => $result->status === PaymentResult::STATUS_FAILED ? ($result->metadata['error_code'] ?? null) : null,
        ];

        if (
            ($model instanceof FundingTransaction || $model instanceof DisbursementTransaction || $model instanceof Repayment) &&
            isset($result->metadata['payment_method'])
        ) {
            $data['payment_method'] = $result->metadata['payment_method'];
        }

        $model->update($data);
    }

    protected function manualResult(string $operation, string $reference): PaymentResult
    {
        return PaymentResult::make(
            success: true,
            status: PaymentResult::STATUS_MANUAL,
            providerName: 'manual',
            externalReference: $reference,
            message: 'Manual execution mode: no provider called.',
            metadata: ['operation' => $operation],
        );
    }

    protected function findTransaction(?string $type, ?int $id): ?Model
    {
        if (! $type || ! $id) {
            return null;
        }

        return match ($type) {
            'funding_transaction' => FundingTransaction::find($id),
            'disbursement_transaction' => DisbursementTransaction::find($id),
            'repayment' => Repayment::find($id),
            'lender_repayment' => LenderRepayment::find($id),
            default => null,
        };
    }

    protected function reconcileWebhookAmount(Model $transaction, WebhookResult $result, string $provider): void
    {
        if ($result->amount === null) {
            return;
        }

        $expected = $this->expectedTransactionAmount($transaction);

        if ($expected === null) {
            return;
        }

        $this->reconciliationService->compareAmounts(
            $transaction,
            $expected,
            $result->amount,
            $provider,
            $result->providerReference,
            [
                'operation' => $this->operationFromTransactionType($transaction),
                'event_type' => $result->eventType,
                'webhook_status' => $result->status,
            ]
        );
    }

    protected function expectedTransactionAmount(Model $transaction): ?float
    {
        return match (get_class($transaction)) {
            DisbursementTransaction::class => (float) $transaction->gross_amount,
            FundingTransaction::class,
            Repayment::class,
            LenderRepayment::class => (float) $transaction->amount,
            default => null,
        };
    }

    protected function operationFromTransactionType(Model $transaction): string
    {
        return match (get_class($transaction)) {
            FundingTransaction::class => PaymentInstruction::OPERATION_LENDER_FUNDING,
            DisbursementTransaction::class => PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT,
            Repayment::class => PaymentInstruction::OPERATION_BORROWER_REPAYMENT,
            LenderRepayment::class => PaymentInstruction::OPERATION_LENDER_RETURN,
            default => 'unknown',
        };
    }

    protected function logOperationStart(PaymentInstruction $instruction, Model $transaction): void
    {
        PaymentAuditLog::log(
            operation: $instruction->operation,
            event: 'execution_started',
            context: [
                'payment_method' => $instruction->paymentMethod,
                'provider' => $instruction->provider,
                'transaction_type' => $this->transactionType($transaction),
                'transaction_id' => $transaction->id,
                'transaction_reference' => $instruction->reference,
                'expected_amount' => $instruction->amount,
            ]
        );
    }

    protected function logOperationResult(PaymentInstruction $instruction, Model $transaction, PaymentResult $result): void
    {
        PaymentAuditLog::log(
            operation: $instruction->operation,
            event: 'execution_result',
            status: $result->status,
            message: $result->message,
            context: [
                'payment_method' => $instruction->paymentMethod,
                'provider' => $result->providerName,
                'transaction_type' => $this->transactionType($transaction),
                'transaction_id' => $transaction->id,
                'transaction_reference' => $instruction->reference,
                'provider_reference' => $result->providerReference,
                'expected_amount' => $instruction->amount,
            ]
        );
    }

    protected function logStatusChange(PaymentInstruction $instruction, Model $transaction, string $newStatus, string $message): void
    {
        PaymentAuditLog::log(
            operation: $instruction->operation,
            event: 'status_changed',
            status: $newStatus,
            message: $message,
            context: [
                'payment_method' => $instruction->paymentMethod,
                'provider' => $instruction->provider,
                'transaction_type' => $this->transactionType($transaction),
                'transaction_id' => $transaction->id,
                'transaction_reference' => $instruction->reference,
                'provider_reference' => $transaction->provider_reference ?? null,
            ]
        );
    }

    protected function logManualFallback(string $operation, Model $transaction): void
    {
        PaymentAuditLog::log(
            operation: $operation,
            event: 'manual_fallback',
            status: PaymentResult::STATUS_MANUAL,
            message: 'Automation disabled or operation configured as manual; falling back to manual workflow.',
            context: [
                'transaction_type' => $this->transactionType($transaction),
                'transaction_id' => $transaction->id,
                'transaction_reference' => $transaction->transaction_reference ?? null,
            ]
        );
    }

    protected function transactionType(Model $transaction): string
    {
        return match (get_class($transaction)) {
            FundingTransaction::class => 'funding_transaction',
            DisbursementTransaction::class => 'disbursement_transaction',
            Repayment::class => 'repayment',
            LenderRepayment::class => 'lender_repayment',
            default => 'unknown',
        };
    }
}
