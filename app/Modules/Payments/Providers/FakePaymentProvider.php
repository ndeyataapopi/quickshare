<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\DTOs\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FakePaymentProvider implements PaymentProviderInterface
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_TIMEOUT = 'timeout';
    public const OUTCOME_REVERSED = 'reversed';
    public const OUTCOME_DUPLICATE = 'duplicate';
    public const OUTCOME_WEBHOOK_DUPLICATE = 'webhook_duplicate';

    protected static ?string $forcedOutcome = null;
    protected static ?bool $forcedSignatureValid = null;

    public function __construct(
        protected ?string $configOutcome = null,
    ) {
    }

    public static function forceOutcome(?string $outcome): void
    {
        self::$forcedOutcome = $outcome;
    }

    public static function clearForcedOutcome(): void
    {
        self::$forcedOutcome = null;
    }

    public static function forceSignatureValid(?bool $valid): void
    {
        self::$forcedSignatureValid = $valid;
    }

    public static function clearForcedSignatureValid(): void
    {
        self::$forcedSignatureValid = null;
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isHealthy(): bool
    {
        return true;
    }

    public function supports(string $operation, string $paymentMethod): bool
    {
        return in_array($paymentMethod, [
            PaymentInstruction::METHOD_PAYMENT_LINK,
            PaymentInstruction::METHOD_DEBIT_ORDER,
            PaymentInstruction::METHOD_BANK_PAYOUT,
            PaymentInstruction::METHOD_WALLET_PAYOUT,
            PaymentInstruction::METHOD_MANUAL,
        ], true);
    }

    public function initiateFunding(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function initiateDisbursement(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function initiateRepayment(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function initiateLenderReturn(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function checkStatus(string $providerReference): PaymentResult
    {
        $outcome = $this->outcome();

        return match ($outcome) {
            self::OUTCOME_PENDING => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_PENDING,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'Fake provider reports payment still pending.',
            ),
            self::OUTCOME_TIMEOUT => PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_TIMEOUT,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'Fake provider status check timed out.',
            ),
            default => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_COMPLETED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                message: 'Fake provider reports payment completed.',
            ),
        };
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        $reference = $payload['reference'] ?? null;
        $providerReference = $payload['provider_reference'] ?? null;
        $providerEventId = $payload['provider_event_id'] ?? null;
        $eventType = $payload['event_type'] ?? 'payment.update';
        $outcome = $this->outcome();

        if ($outcome === self::OUTCOME_WEBHOOK_DUPLICATE) {
            return WebhookResult::handled(
                eventType: 'webhook.duplicate',
                reference: $reference,
                providerReference: $providerReference,
                providerEventId: $providerEventId,
                status: PaymentResult::STATUS_DUPLICATE,
                message: 'Fake provider detected a duplicate webhook delivery.',
                metadata: ['duplicate' => true],
            );
        }

        $status = match ($outcome) {
            self::OUTCOME_SUCCESS, self::OUTCOME_DUPLICATE => PaymentResult::STATUS_COMPLETED,
            self::OUTCOME_PENDING => PaymentResult::STATUS_PENDING,
            self::OUTCOME_FAILED => PaymentResult::STATUS_FAILED,
            self::OUTCOME_TIMEOUT => PaymentResult::STATUS_TIMEOUT,
            self::OUTCOME_REVERSED => PaymentResult::STATUS_REVERSED,
            default => PaymentResult::STATUS_COMPLETED,
        };

        return WebhookResult::handled(
            eventType: $eventType,
            reference: $reference,
            providerReference: $providerReference,
            providerEventId: $providerEventId,
            status: $status,
            amount: $payload['amount'] ?? null,
            currency: $payload['currency'] ?? null,
            message: "Fake provider processed webhook as {$outcome}.",
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return self::$forcedSignatureValid ?? true;
    }

    protected function execute(PaymentInstruction $instruction): PaymentResult
    {
        $providerReference = 'FAKE-' . strtoupper(Str::random(12));
        $outcome = $this->outcome();

        $metadata = [
            'operation' => $instruction->operation,
            'payment_method' => $instruction->paymentMethod,
            'execution_mode' => $instruction->executionMode,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'simulated_outcome' => $outcome,
        ];

        return match ($outcome) {
            self::OUTCOME_SUCCESS => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_COMPLETED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated successful execution.',
                metadata: $metadata,
            ),
            self::OUTCOME_PENDING => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_PENDING,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated pending execution.',
                metadata: $metadata,
            ),
            self::OUTCOME_FAILED => PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_FAILED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated failure.',
                metadata: $metadata,
            ),
            self::OUTCOME_TIMEOUT => PaymentResult::make(
                success: false,
                status: PaymentResult::STATUS_TIMEOUT,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated timeout.',
                metadata: $metadata,
            ),
            self::OUTCOME_REVERSED => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_REVERSED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated reversal.',
                metadata: $metadata,
            ),
            self::OUTCOME_DUPLICATE, self::OUTCOME_WEBHOOK_DUPLICATE => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_DUPLICATE,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated duplicate transaction.',
                metadata: array_merge($metadata, ['duplicate' => true]),
            ),
            default => PaymentResult::make(
                success: true,
                status: PaymentResult::STATUS_COMPLETED,
                providerName: $this->getName(),
                providerReference: $providerReference,
                externalReference: $instruction->reference,
                message: 'Fake provider simulated successful execution.',
                metadata: $metadata,
            ),
        };
    }

    protected function outcome(): string
    {
        return self::$forcedOutcome
            ?? $this->configOutcome
            ?? config('payment_providers.providers.fake.outcome', self::OUTCOME_SUCCESS);
    }
}
