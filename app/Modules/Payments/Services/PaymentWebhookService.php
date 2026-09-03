<?php

namespace App\Modules\Payments\Services;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Payments\DTOs\WebhookProcessResult;
use App\Modules\Payments\DTOs\WebhookResult;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function __construct(
        protected PaymentConfigurationResolver $configurationResolver,
    ) {
    }

    /**
     * Process a verified provider webhook result.
     *
     * Flow:
     *   1. Idempotency / replay check.
     *   2. Persist the webhook event.
     *   3. Identify the internal transaction.
     *   4. Validate reference and amount.
     *   5. Mark processed exactly once.
     */
    public function process(WebhookResult $result, string $provider, Request $request): WebhookProcessResult
    {
        $providerEventId = $result->providerEventId ?? null;
        $providerReference = $result->providerReference ?? $result->reference ?? null;
        $eventType = $result->eventType ?? 'unknown';
        $payload = array_merge($result->metadata, [
            'event_type' => $eventType,
            'provider_reference' => $providerReference,
            'provider_event_id' => $providerEventId,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'status' => $result->status,
        ]);

        // 1. Idempotency / replay check using provider_event_id when available.
        if ($providerEventId) {
            $existing = PaymentWebhookEvent::where('provider', $provider)
                ->where('provider_event_id', $providerEventId)
                ->first();

            if ($existing) {
                return WebhookProcessResult::duplicate($existing, 'Duplicate provider event id.');
            }
        }

        // 2. Persist the event as early as possible for audit and replay protection.
        $event = PaymentWebhookEvent::create([
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
            'provider_reference' => $providerReference,
            'event_type' => $eventType,
            'payload' => $payload,
            'signature' => $request->header('X-Signature') ?? $request->header('Signature'),
            'ip_address' => $request->ip(),
            'status' => PaymentWebhookEvent::STATUS_RECEIVED,
        ]);

        // Redelivered event with same provider_event_id is already handled above.
        // Redelivered event with same provider_reference + event type is treated as a
        // duplicate after the first one is processed, even if the provider sent a new
        // event identifier.
        if ($providerReference) {
            $existingReference = PaymentWebhookEvent::where('provider', $provider)
                ->where('provider_reference', $providerReference)
                ->where('event_type', $eventType)
                ->where('status', PaymentWebhookEvent::STATUS_PROCESSED)
                ->where('id', '!=', $event->id)
                ->first();

            if ($existingReference) {
                $event->update(['status' => PaymentWebhookEvent::STATUS_DUPLICATE]);

                return WebhookProcessResult::duplicate($event, 'Duplicate provider reference for event type.');
            }
        }

        // 3. Identify internal transaction.
        $transaction = $this->identifyTransaction($providerReference);

        if (! $transaction && $providerReference) {
            $event->update([
                'status' => PaymentWebhookEvent::STATUS_INVALID,
                'error_message' => 'No internal transaction found for provider reference.',
            ]);

            return WebhookProcessResult::invalid('No internal transaction found for provider reference.');
        }

        // 4. Validate reference presence.
        if (! $providerReference) {
            $event->update([
                'status' => PaymentWebhookEvent::STATUS_INVALID,
                'error_message' => 'Missing provider reference in webhook payload.',
            ]);

            return WebhookProcessResult::invalid('Missing provider reference in webhook payload.');
        }

        // 5. Validate amount if present in payload.
        if ($result->amount !== null && $transaction) {
            $transactionAmount = (float) $this->transactionAmount($transaction);
            $webhookAmount = (float) $result->amount;

            if (round($transactionAmount, 2) !== round($webhookAmount, 2)) {
                $event->update([
                    'status' => PaymentWebhookEvent::STATUS_INVALID,
                    'error_message' => 'Webhook amount does not match transaction amount.',
                    'transaction_type' => $this->transactionType($transaction),
                    'transaction_id' => $transaction->id,
                ]);

                return WebhookProcessResult::invalid('Webhook amount does not match transaction amount.');
            }
        }

        // 6. Mark processed exactly once.
        $event->update([
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => $transaction ? $this->transactionType($transaction) : null,
            'transaction_id' => $transaction?->id,
            'processed_at' => now(),
        ]);

        Log::info('Payment webhook processed', [
            'provider' => $provider,
            'event_id' => $event->id,
            'provider_event_id' => $providerEventId,
            'provider_reference' => $providerReference,
            'transaction_type' => $event->transaction_type,
            'transaction_id' => $event->transaction_id,
        ]);

        // Business state changes are intentionally NOT performed here.
        // The verified result should be handed to a business service in a later phase.
        return WebhookProcessResult::processed($event);
    }

    /**
     * Find the internal transaction by provider reference across all supported tables.
     */
    protected function identifyTransaction(?string $providerReference): ?object
    {
        if (! $providerReference) {
            return null;
        }

        return FundingTransaction::where('provider_reference', $providerReference)->first()
            ?? DisbursementTransaction::where('provider_reference', $providerReference)->first()
            ?? Repayment::where('provider_reference', $providerReference)->first()
            ?? LenderRepayment::where('provider_reference', $providerReference)->first();
    }

    protected function transactionType(object $transaction): string
    {
        return match (get_class($transaction)) {
            FundingTransaction::class => 'funding_transaction',
            DisbursementTransaction::class => 'disbursement_transaction',
            Repayment::class => 'repayment',
            LenderRepayment::class => 'lender_repayment',
            default => 'unknown',
        };
    }

    protected function transactionAmount(object $transaction): float
    {
        $value = match (get_class($transaction)) {
            DisbursementTransaction::class => $transaction->gross_amount,
            FundingTransaction::class,
            Repayment::class,
            LenderRepayment::class => $transaction->amount,
            default => 0,
        };

        return (float) $value;
    }
}
