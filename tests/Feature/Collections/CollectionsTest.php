<?php

namespace Tests\Feature\Collections;

use App\Models\User;
use App\Modules\Collections\Models\CollectionLog;
use App\Modules\Collections\Services\CollectionService;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    protected CollectionService $service;
    protected User $borrower;
    protected User $referrer;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(CollectionService::class);

        $this->referrer = User::factory()->active()->create(['trust_score' => 70.00]);
        $this->referrer->assignRole('lender');

        $this->borrower = User::factory()->active()->create([
            'trust_score' => 65.00,
            'referred_by' => $this->referrer->id,
        ]);
        $this->borrower->assignRole('borrower');

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->admin->assignRole('admin');
        $this->admin = $this->admin->fresh();
    }

    protected function createActiveLoan(array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15.00,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'funded_amount' => 10000,
            'loan_term_days' => 60,
            'status' => 'active',
            'repayment_date' => now()->addDays(60),
            'risk_score' => 65.00,
            'submitted_at' => now(),
            'approved_at' => now(),
            'disbursed_at' => now(),
        ], $overrides));
    }

    // ─── Reminder Tests ──────────────────────────────────────────────

    public function test_reminder_can_be_sent(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $log = $this->service->sendReminder($loan, $repayment, 'sms', 'pre_due');

        $this->assertNotNull($log->id);
        $this->assertEquals('reminder_sent', $log->action_type);
        $this->assertEquals('sms', $log->channel);
        $this->assertEquals('pending', $log->status);
    }

    public function test_duplicate_reminder_prevented(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        // First reminder
        $log1 = $this->service->sendReminder($loan, $repayment, 'sms', 'pre_due');
        
        // Second reminder should be skipped (same day)
        $log2 = $this->service->sendReminder($loan, $repayment, 'sms', 'pre_due');

        $this->assertEquals($log1->id, $log2->id);
    }

    public function test_daily_reminders_processed(): void
    {
        $loan = $this->createActiveLoan();
        
        // Pre-due repayment
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(7), // 7 days before = reminder
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $stats = $this->service->processDailyReminders();

        $this->assertGreaterThan(0, $stats['sent']);
    }

    // ─── Escalation Tests ────────────────────────────────────────────

    public function test_escalation_processed(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(8), // 8 days overdue = level 1
            'days_overdue' => 8,
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $stats = $this->service->processEscalations();

        $this->assertEquals(1, $stats['escalation_level_1']);
    }

    public function test_escalation_applies_trust_score_penalty(): void
    {
        $initialScore = $this->borrower->trust_score;

        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(8),
            'days_overdue' => 8,
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->service->processEscalations();

        $this->borrower->refresh();
        $this->assertLessThan($initialScore, (float) $this->borrower->trust_score);
    }

    // ─── Referral Accountability Tests ───────────────────────────────

    public function test_referrer_notified_on_escalation(): void
    {
        Queue::fake();

        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(8),
            'days_overdue' => 8,
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->service->processEscalations();

        $this->assertDatabaseHas('collection_logs', [
            'loan_id' => $loan->id,
            'action_type' => 'referral_notified',
        ]);
    }

    public function test_referrer_penalized_on_default(): void
    {
        $referrerInitialScore = $this->referrer->trust_score;

        $loan = $this->createActiveLoan(['status' => 'defaulted']);

        $this->service->processDefaultWorkflow($loan);

        $this->referrer->refresh();
        $this->assertLessThan($referrerInitialScore, (float) $this->referrer->trust_score);
    }

    // ─── Default Workflow Tests ──────────────────────────────────────

    public function test_default_workflow_processed(): void
    {
        $initialScore = $this->borrower->trust_score;

        $loan = $this->createActiveLoan(['status' => 'defaulted']);
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'defaulted',
            'due_date' => now()->subDays(45),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->service->processDefaultWorkflow($loan);

        $this->borrower->refresh();
        $this->assertLessThan($initialScore, (float) $this->borrower->trust_score);

        $this->assertDatabaseHas('collection_logs', [
            'loan_id' => $loan->id,
            'action_type' => 'default_initiated',
        ]);

        $this->assertDatabaseHas('collection_logs', [
            'loan_id' => $loan->id,
            'action_type' => 'default_processed',
        ]);
    }

    // ─── API Tests ───────────────────────────────────────────────────

    public function test_admin_can_view_collections_dashboard(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/collections/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'overdue_today',
                    'overdue_week',
                    'escalation_level_1',
                    'escalation_level_2',
                    'escalation_level_3',
                    'reminders_sent_today',
                    'delivery_rate',
                ],
            ]);
    }

    public function test_admin_can_view_loan_collection_history(): void
    {
        $loan = $this->createActiveLoan();
        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/collections/loan/{$loan->id}/history");

        $response->assertOk()
            ->assertJsonCount(1, 'data.history');
    }

    public function test_admin_can_view_borrower_collection_stats(): void
    {
        $loan = $this->createActiveLoan();
        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'delivered',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/collections/borrower/{$this->borrower->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.total_reminders', 1);
    }

    public function test_admin_can_trigger_daily_reminders(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/collections/trigger-reminders');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_send_manual_reminder(): void
    {
        Queue::fake();

        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/collections/repayment/{$repayment->id}/send-reminder", [
            'channel' => 'email',
            'message' => 'Custom reminder message',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.log.channel', 'email');
    }

    public function test_admin_can_process_escalations(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/collections/process-escalations');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['escalation_level_1', 'escalation_level_2', 'escalation_level_3'],
            ]);
    }

    public function test_admin_can_view_collection_logs(): void
    {
        $loan = $this->createActiveLoan();
        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'delivered',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/collections/logs');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_update_log_status(): void
    {
        $loan = $this->createActiveLoan();
        $log = CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'sent',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/collections/logs/{$log->id}/update-status", [
            'status' => 'delivered',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.log.status', 'delivered');
    }

    // ─── Model Tests ───────────────────────────────────────────────────

    public function test_collection_log_scopes(): void
    {
        $loan = $this->createActiveLoan();
        
        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'delivered',
        ]);

        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'escalation_level_1',
            'channel' => 'email',
            'status' => 'delivered',
        ]);

        $this->assertEquals(1, CollectionLog::reminders()->count());
        $this->assertEquals(1, CollectionLog::escalations()->count());
        $this->assertEquals(2, CollectionLog::byChannel('sms')->count() + CollectionLog::byChannel('email')->count());
    }

    public function test_loan_has_collection_logs_relationship(): void
    {
        $loan = $this->createActiveLoan();
        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'delivered',
        ]);

        $this->assertCount(1, $loan->fresh()->collectionLogs);
    }

    public function test_user_has_collection_logs_relationship(): void
    {
        $loan = $this->createActiveLoan();
        CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'delivered',
        ]);

        $this->assertCount(1, $this->borrower->fresh()->collectionLogs);
    }

    public function test_collection_log_status_helpers(): void
    {
        $loan = $this->createActiveLoan();
        $log = CollectionLog::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'action_type' => 'reminder_sent',
            'channel' => 'sms',
            'status' => 'pending',
        ]);

        $this->assertTrue($log->isPending());
        $this->assertFalse($log->isDelivered());

        $log->markAsSent('EXT-123');
        $this->assertTrue($log->fresh()->isSent());

        $log->markAsDelivered();
        $this->assertTrue($log->fresh()->isDelivered());
    }
}
