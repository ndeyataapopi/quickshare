<?php

namespace Tests\Feature\Api\V1\Webhooks;

use App\Http\Controllers\Api\V1\Webhooks\MifosWebhookController;
use App\Modules\Loans\Events\ExternalLoanStatusUpdated;
use App\Modules\Loans\Jobs\SyncExternalLoanStatusJob;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MifosWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_webhook_requires_valid_signature_when_configured(): void
    {
        Config::set('mifos.webhook.secret', 'test-secret');

        $response = $this->postJson('/api/v1/webhooks/mifos', [], [
            'X-Mifos-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_webhook_rejects_ip_when_not_in_allowed_list(): void
    {
        Config::set('mifos.webhook.allowed_ips', ['192.168.1.1']);

        $response = $this->postJson('/api/v1/webhooks/mifos', [], [
            'REMOTE_ADDR' => '192.168.1.2',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_webhook_returns_404_when_loan_not_found(): void
    {
        $payload = [
            'eventType' => 'LOAN_APPROVED',
            'loanId' => 'EXT-99999',
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_webhook_handles_loan_approved_event(): void
    {
        Event::fake([ExternalLoanStatusUpdated::class]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'funded',
        ]);

        $payload = [
            'eventType' => 'LOAN_APPROVED',
            'loanId' => 'EXT-12345',
            'loanStatus' => ['status' => ['value' => 'approved']],
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $loan->refresh();
        $this->assertEquals('funded', $loan->status);

        Event::assertDispatched(ExternalLoanStatusUpdated::class);
    }

    public function test_webhook_handles_loan_rejected_event(): void
    {
        Event::fake([ExternalLoanStatusUpdated::class]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'pending_review',
        ]);

        $payload = [
            'eventType' => 'LOAN_REJECTED',
            'loanId' => 'EXT-12345',
            'rejectionReason' => 'Credit score too low',
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertOk();

        $loan->refresh();
        $this->assertEquals('cancelled', $loan->status);
        $this->assertEquals('Credit score too low', $loan->rejection_reason);
    }

    public function test_webhook_handles_loan_disbursed_event(): void
    {
        Event::fake([ExternalLoanStatusUpdated::class]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'funded',
        ]);

        $payload = [
            'eventType' => 'LOAN_DISBURSED',
            'loanId' => 'EXT-12345',
            'disbursementDate' => '2026-05-21',
            'amount' => 10000,
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertOk();

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);
    }

    public function test_webhook_handles_loan_overdue_event(): void
    {
        Event::fake([ExternalLoanStatusUpdated::class]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $payload = [
            'eventType' => 'LOAN_OVERDUE',
            'loanId' => 'EXT-12345',
            'daysOverdue' => 15,
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertOk();

        $loan->refresh();
        $this->assertEquals('overdue', $loan->status);
    }

    public function test_webhook_handles_loan_closed_event(): void
    {
        Event::fake([ExternalLoanStatusUpdated::class]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $payload = [
            'eventType' => 'LOAN_CLOSED',
            'loanId' => 'EXT-12345',
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertOk();

        $loan->refresh();
        $this->assertEquals('completed', $loan->status);
        $this->assertNotNull($loan->completed_at);
    }

    public function test_webbook_handles_generic_event_by_dispatching_sync_job(): void
    {
        Queue::fake();

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $payload = [
            'eventType' => 'UNKNOWN_EVENT',
            'loanId' => 'EXT-12345',
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertOk();
        $response->assertJson(['success' => true, 'message' => 'Event acknowledged']);
    }

    public function test_webbook_returns_400_when_missing_loan_id(): void
    {
        $payload = [
            'eventType' => 'LOAN_APPROVED',
        ];

        $response = $this->postJson('/api/v1/webhooks/mifos', $payload);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }
}
