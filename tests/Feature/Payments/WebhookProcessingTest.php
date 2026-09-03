<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\Providers\FakePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakePaymentProvider::clearForcedOutcome();
        FakePaymentProvider::clearForcedSignatureValid();
    }

    protected function postWebhook(array $payload, string $provider = 'fake'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/payments/webhooks/{$provider}", $payload);
    }

    protected function createFundingTransaction(float $amount = 1000.00, string $providerReference = 'FAKE-123'): FundingTransaction
    {
        return FundingTransaction::factory()->create([
            'loan_id' => \App\Modules\Loans\Models\Loan::factory()->create(),
            'lender_id' => User::factory()->create(),
            'amount' => $amount,
            'provider_reference' => $providerReference,
        ]);
    }

    // ─── Valid Webhook ─────────────────────────────────────────────────

    public function test_valid_webhook_is_processed(): void
    {
        $transaction = $this->createFundingTransaction();

        $response = $this->postWebhook([
            'provider_event_id' => 'evt-valid-1',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
            'currency' => 'NAD',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Webhook processed.');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'fake',
            'provider_event_id' => 'evt-valid-1',
            'provider_reference' => 'FAKE-123',
            'status' => PaymentWebhookEvent::STATUS_PROCESSED,
            'transaction_type' => 'funding_transaction',
            'transaction_id' => $transaction->id,
        ]);

        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertTrue(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    // ─── Invalid Signature ───────────────────────────────────────────────

    public function test_invalid_signature_is_rejected(): void
    {
        FakePaymentProvider::forceSignatureValid(false);

        $this->createFundingTransaction();

        $response = $this->postWebhook([
            'provider_event_id' => 'evt-invalid-sig',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid signature.');

        $this->assertDatabaseMissing('payment_webhook_events', [
            'provider_event_id' => 'evt-invalid-sig',
        ]);
    }

    // ─── Duplicate Webhook ─────────────────────────────────────────────

    public function test_duplicate_webhook_is_not_processed_twice(): void
    {
        $transaction = $this->createFundingTransaction();

        $payload = [
            'provider_event_id' => 'evt-dup-1',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ];

        $first = $this->postWebhook($payload);
        $first->assertOk();

        $second = $this->postWebhook($payload);
        $second->assertOk()
            ->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertCount(1, PaymentWebhookEvent::where('provider_event_id', 'evt-dup-1')->get());
        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertTrue(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    // ─── Unknown Transaction ─────────────────────────────────────────────

    public function test_unknown_transaction_is_rejected(): void
    {
        $response = $this->postWebhook([
            'provider_event_id' => 'evt-unknown-1',
            'provider_reference' => 'FAKE-NOT-FOUND',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'No internal transaction found for provider reference.');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-unknown-1',
            'status' => PaymentWebhookEvent::STATUS_INVALID,
        ]);
    }

    // ─── Missing Reference ───────────────────────────────────────────────

    public function test_missing_reference_is_rejected(): void
    {
        $response = $this->postWebhook([
            'provider_event_id' => 'evt-missing-ref',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Missing provider reference in webhook payload.');
    }

    // ─── Wrong Amount ────────────────────────────────────────────────────

    public function test_wrong_amount_is_rejected(): void
    {
        $transaction = $this->createFundingTransaction(1000.00);

        $response = $this->postWebhook([
            'provider_event_id' => 'evt-wrong-amount',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 500.00,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Webhook amount does not match transaction amount.');

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-wrong-amount',
            'transaction_id' => $transaction->id,
            'status' => PaymentWebhookEvent::STATUS_INVALID,
        ]);
    }

    // ─── Replayed Webhook ────────────────────────────────────────────────

    public function test_replayed_webhook_with_same_event_id_is_duplicate(): void
    {
        $this->createFundingTransaction();

        $payload = [
            'provider_event_id' => 'evt-replay-1',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk()->assertJsonPath('message', 'Duplicate provider event id.');

        $this->assertCount(1, PaymentWebhookEvent::all());
    }

    // ─── Provider Event With No Internal Transaction ─────────────────────

    public function test_provider_event_with_no_internal_transaction_is_rejected(): void
    {
        $response = $this->postWebhook([
            'provider_event_id' => 'evt-orphan-1',
            'provider_reference' => 'FAKE-ORPHAN',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-orphan-1',
            'status' => PaymentWebhookEvent::STATUS_INVALID,
        ]);
    }

    // ─── Same Provider Reference On Duplicate Request ────────────────────

    public function test_same_provider_reference_on_duplicate_request_is_detected(): void
    {
        $transaction = $this->createFundingTransaction();

        $this->postWebhook([
            'provider_event_id' => 'evt-same-ref-a',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ])->assertOk();

        $this->postWebhook([
            'provider_event_id' => 'evt-same-ref-b',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ])->assertOk()->assertJsonPath('message', 'Duplicate provider reference for event type.');

        $this->assertCount(1, PaymentWebhookEvent::where('provider_reference', 'FAKE-123')->where('status', PaymentWebhookEvent::STATUS_PROCESSED)->get());
        $this->assertCount(1, PaymentWebhookEvent::where('provider_reference', 'FAKE-123')->where('status', PaymentWebhookEvent::STATUS_DUPLICATE)->get());
        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertCount(1, Investment::where('funding_transaction_id', $transaction->id)->get());
    }

    // ─── Business State Is Driven By Webhook Layer ───────────────────────

    public function test_webhook_drives_business_action_for_known_transaction(): void
    {
        $transaction = $this->createFundingTransaction();

        $this->postWebhook([
            'provider_event_id' => 'evt-no-business-1',
            'provider_reference' => 'FAKE-123',
            'event_type' => 'payment.completed',
            'amount' => 1000.00,
        ])->assertOk();

        $this->assertSame('confirmed', $transaction->fresh()->status);
        $this->assertTrue(Investment::where('funding_transaction_id', $transaction->id)->exists());
    }

    // ─── Disbursement Transaction Identification ─────────────────────────

    public function test_webhook_can_identify_disbursement_transaction(): void
    {
        $loan = \App\Modules\Loans\Models\Loan::factory()->create();
        $disbursement = DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'gross_amount' => 2000.00,
            'platform_fee' => 100.00,
            'net_amount' => 1900.00,
            'direction' => 'outgoing',
            'status' => 'awaiting_disbursement',
            'transaction_reference' => 'DISB-' . strtoupper(\Illuminate\Support\Str::random(12)),
            'provider_reference' => 'FAKE-DISB-1',
        ]);

        $response = $this->postWebhook([
            'provider_event_id' => 'evt-disb-1',
            'provider_reference' => 'FAKE-DISB-1',
            'event_type' => 'payout.completed',
            'amount' => 2000.00,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider_event_id' => 'evt-disb-1',
            'transaction_type' => 'disbursement_transaction',
            'transaction_id' => $disbursement->id,
        ]);

        $this->assertSame('pending_borrower_confirmation', $disbursement->fresh()->status);
    }
}
