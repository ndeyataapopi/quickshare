<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\DTOs\WebhookResult;
use Illuminate\Http\Request;

class ManualPaymentProvider implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'manual';
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
        return $paymentMethod === PaymentInstruction::METHOD_MANUAL;
    }

    public function initiateFunding(PaymentInstruction $instruction): PaymentResult
    {
        return $this->manualResult($instruction);
    }

    public function initiateDisbursement(PaymentInstruction $instruction): PaymentResult
    {
        return $this->manualResult($instruction);
    }

    public function initiateRepayment(PaymentInstruction $instruction): PaymentResult
    {
        return $this->manualResult($instruction);
    }

    public function initiateLenderReturn(PaymentInstruction $instruction): PaymentResult
    {
        return $this->manualResult($instruction);
    }

    public function checkStatus(string $providerReference): PaymentResult
    {
        return PaymentResult::make(
            success: true,
            status: PaymentResult::STATUS_MANUAL,
            providerName: $this->getName(),
            providerReference: $providerReference,
            message: 'Manual payment status is tracked outside the provider.',
        );
    }

    public function handleWebhook(array $payload): WebhookResult
    {
        return WebhookResult::notHandled(message: 'Manual provider does not process webhooks.');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return false;
    }

    protected function manualResult(PaymentInstruction $instruction): PaymentResult
    {
        return PaymentResult::make(
            success: true,
            status: PaymentResult::STATUS_MANUAL,
            providerName: $this->getName(),
            externalReference: $instruction->reference,
            message: 'Payment is manual. Money moves outside QuickShare; existing confirmation workflow applies.',
            metadata: [
                'operation' => $instruction->operation,
                'payment_method' => $instruction->paymentMethod,
                'execution_mode' => $instruction->executionMode,
                'amount' => $instruction->amount,
                'currency' => $instruction->currency,
            ],
        );
    }
}
