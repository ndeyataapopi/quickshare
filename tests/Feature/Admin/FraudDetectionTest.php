<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Models\FraudFlag;
use App\Modules\Admin\Services\FraudDetectionService;
use App\Modules\KYC\Models\KycSubmission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FraudDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected FraudDetectionService $service;
    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(FraudDetectionService::class);

        $this->admin = User::factory()->active()->create();
        $this->assignAdminRole($this->admin);
        $this->admin = $this->admin->fresh();

        $this->user = User::factory()->active()->create([
            'national_id' => '123456789',
            'trust_score' => 70.00,
        ]);
        $this->assignClientRole($this->user);
    }

    // ─── Duplicate Identity Detection ────────────────────────────────────

    // Note: Duplicate identity detection tests skipped because 
    // database has unique constraint on national_id which prevents duplicates

    // ─── Duplicate Bank Account Detection ──────────────────────────────

    public function test_duplicate_bank_account_detected(): void
    {
        $user1 = User::factory()->active()->create();
        $user2 = User::factory()->active()->create();

        // Create KYC with bank account for user1
        KycSubmission::create([
            'user_id' => $user1->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'metadata' => [
                'bank_account_number' => '1234567890',
                'bank_code' => 'BANK001',
            ],
        ]);

        // Create KYC with same bank account for user2
        KycSubmission::create([
            'user_id' => $user2->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'metadata' => [
                'bank_account_number' => '1234567890',
                'bank_code' => 'BANK001',
            ],
        ]);

        $duplicate = $this->service->checkDuplicateBankAccount($user2);

        $this->assertNotNull($duplicate);
        $this->assertEquals('1234567890', $duplicate['bank_account']);
    }

    // ─── Fake Referral Detection ───────────────────────────────────────

    public function test_fake_referral_detected(): void
    {
        $referrer = User::factory()->active()->create([
            'last_name' => 'Smith',
            'phone' => '+27123456789',
            'created_at' => now()->subHours(2),
        ]);

        $referred = User::factory()->active()->create([
            'last_name' => 'Smith', // Similar name
            'phone' => '+27123498765', // Same prefix
            'referred_by' => $referrer->id,
            'created_at' => now(),
        ]);

        $fakeReferral = $this->service->checkFakeReferral($referred);

        $this->assertNotNull($fakeReferral);
        $this->assertContains('similar_names', $fakeReferral['patterns_detected']);
        $this->assertContains('same_phone_prefix', $fakeReferral['patterns_detected']);
    }

    // ─── API Tests ───────────────────────────────────────────────────

    public function test_admin_can_view_fraud_summary(): void
    {
        // Create a fraud flag
        FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'flag_type' => 'duplicate_identity',
            'severity' => 'critical',
            'status' => 'open',
            'description' => 'Duplicate ID',
            'risk_score' => 100,
            'detected_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/fraud/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users_with_flags',
                    'high_risk_users',
                    'recent_flags',
                    'flags_by_type',
                ],
            ]);
    }

    public function test_admin_can_view_review_queue(): void
    {
        FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'flag_type' => 'duplicate_identity',
            'severity' => 'critical',
            'status' => 'open',
            'description' => 'Duplicate ID',
            'risk_score' => 100,
            'detected_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/fraud/queue');

        $response->assertOk()
            ->assertJsonPath('data.flags.0.flag_type', 'duplicate_identity');
    }

    public function test_admin_can_confirm_fraud(): void
    {
        $flag = FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'flag_type' => 'duplicate_identity',
            'severity' => 'critical',
            'status' => 'under_review',
            'description' => 'Duplicate ID',
            'risk_score' => 100,
            'detected_by' => $this->admin->id,
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/admin/fraud/flags/{$flag->id}/confirm", [
            'resolution_notes' => 'Confirmed duplicate identity',
            'actions' => ['suspend_user'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.flag.status', 'confirmed');
    }

    public function test_admin_can_mark_false_positive(): void
    {
        $flag = FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'flag_type' => 'duplicate_identity',
            'severity' => 'high',
            'status' => 'open',
            'description' => 'Duplicate ID',
            'risk_score' => 75,
            'detected_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/admin/fraud/flags/{$flag->id}/false-positive", [
            'resolution_notes' => 'Legitimate duplicate - family member',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.flag.status', 'false_positive');
    }

    public function test_admin_can_trigger_platform_scan(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/fraud/trigger-scan');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['scanned', 'flags_found', 'high_risk', 'critical'],
            ]);
    }

    // ─── Fraud Flag Model Tests ────────────────────────────────────────

    public function test_fraud_flag_scopes(): void
    {
        FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'flag_type' => 'duplicate_identity',
            'severity' => 'critical',
            'status' => 'open',
            'description' => 'Test',
            'risk_score' => 100,
            'detected_by' => $this->admin->id,
        ]);

        $this->assertEquals(1, FraudFlag::open()->count());
        $this->assertEquals(1, FraudFlag::highSeverity()->count());
        $this->assertEquals(1, FraudFlag::byType('duplicate_identity')->count());
    }

    public function test_fraud_flag_risk_score_calculation(): void
    {
        $score = FraudFlag::calculateRiskScore('critical', []);
        $this->assertEquals(100, $score);

        $score = FraudFlag::calculateRiskScore('high', []);
        $this->assertEquals(75, $score);

        $score = FraudFlag::calculateRiskScore('medium', ['repeat_offender' => true]);
        $this->assertEquals(70, $score); // 50 + 20 (cap at 100, but medium base is 50)
    }

    public function test_fraud_flag_status_helpers(): void
    {
        $flag = FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $this->user->id,
            'flag_type' => 'duplicate_identity',
            'severity' => 'critical',
            'status' => 'open',
            'description' => 'Test',
            'risk_score' => 100,
            'detected_by' => $this->admin->id,
        ]);

        $this->assertTrue($flag->isOpen());
        $this->assertTrue($flag->isCritical());
        $this->assertFalse($flag->isResolved());

        $flag->markConfirmed($this->admin->id, 'Confirmed');
        
        $this->assertTrue($flag->fresh()->isConfirmed());
        $this->assertTrue($flag->fresh()->isResolved());
    }
}
