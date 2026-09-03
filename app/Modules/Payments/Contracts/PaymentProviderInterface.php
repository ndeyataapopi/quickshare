<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\DTOs\WebhookResult;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    /**
     * Initiate a lender funding payment.
     */
    public function initiateFunding(PaymentInstruction $instruction): PaymentResult;

    /**
     * Initiate a borrower disbursement payment.
     */
    public function initiateDisbursement(PaymentInstruction $instruction): PaymentResult;

    /**
     * Initiate a borrower repayment collection.
     */
    public function initiateRepayment(PaymentInstruction $instruction): PaymentResult;

    /**
     * Initiate a lender return distribution.
     */
    public function initiateLenderReturn(PaymentInstruction $instruction): PaymentResult;

    /**
     * Get the provider name (e.g. 'manual', 'fake', 'stripe').
     */
    public function getName(): string;

    /**
     * Whether the provider is minimally configured.
     */
    public function isConfigured(): bool;

    /**
     * Whether the provider is reachable/healthy.
     */
    public function isHealthy(): bool;

    /**
     * Whether this provider supports a given operation + payment method.
     */
    public function supports(string $operation, string $paymentMethod): bool;

    /**
     * Check the status of a previously initiated payment.
     */
    public function checkStatus(string $providerReference): PaymentResult;

    /**
     * Handle an incoming provider webhook payload.
     */
    public function handleWebhook(array $payload): WebhookResult;

    /**
     * Verify a webhook request signature.
     */
    public function verifyWebhookSignature(Request $request): bool;
}
