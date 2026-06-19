<?php

namespace Tests\Feature\TrustScore;

use App\Models\User;
use App\Modules\TrustScore\Models\TrustScoreHistory;
use App\Modules\TrustScore\Services\TrustScoreService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrustScoreTest extends TestCase
{
    use RefreshDatabase;

    protected TrustScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(TrustScoreService::class);
    }

    protected function createUser(float $score = 50.00): User
    {
        $user = User::factory()->active()->create(['trust_score' => $score]);
        $user->assignRole('borrower');

        return $user;
    }

    // ─── Score Adjustment Tests ──────────────────────────────────────

    public function test_on_time_repayment_increases_score(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onRepaymentMade($user, 1000.00, 1);

        $this->assertEquals(53.00, (float) $user->fresh()->trust_score);
        $this->assertDatabaseHas('trust_score_histories', [
            'user_id' => $user->id,
            'event_type' => 'repayment_on_time',
            'change' => 3.00,
        ]);
    }

    public function test_overdue_repayment_decreases_score(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onRepaymentOverdue($user, 5, 1);

        // -5.00 base + (5 * -0.5 = -2.5) = -7.50
        $this->assertEquals(42.50, (float) $user->fresh()->trust_score);
        $this->assertDatabaseHas('trust_score_histories', [
            'user_id' => $user->id,
            'event_type' => 'repayment_overdue',
        ]);
    }

    public function test_overdue_penalty_is_capped_at_default_weight(): void
    {
        $user = $this->createUser(50.00);

        // 30 days overdue: -5 + (30 * -0.5) = -20, capped at -15
        $this->service->onRepaymentOverdue($user, 30, 1);

        $this->assertEquals(35.00, (float) $user->fresh()->trust_score);
    }

    public function test_loan_default_heavily_decreases_score(): void
    {
        $user = $this->createUser(60.00);

        $this->service->onLoanDefault($user, 1);

        $this->assertEquals(45.00, (float) $user->fresh()->trust_score);
        $this->assertDatabaseHas('trust_score_histories', [
            'user_id' => $user->id,
            'event_type' => 'loan_default',
            'change' => -15.00,
        ]);
    }

    public function test_loan_fully_repaid_increases_score(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onLoanFullyRepaid($user, 1);

        $this->assertEquals(55.00, (float) $user->fresh()->trust_score);
    }

    public function test_kyc_approval_increases_score(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onKycApproved($user);

        $this->assertEquals(60.00, (float) $user->fresh()->trust_score);
    }

    public function test_referral_completed_increases_referrer_score(): void
    {
        $referrer = $this->createUser(50.00);

        $this->service->onReferralCompleted($referrer, 99);

        $this->assertEquals(52.00, (float) $referrer->fresh()->trust_score);
    }

    public function test_referral_defaulted_decreases_referrer_score(): void
    {
        $referrer = $this->createUser(50.00);

        $this->service->onReferralDefaulted($referrer, 99);

        $this->assertEquals(47.00, (float) $referrer->fresh()->trust_score);
    }

    // ─── Score Clamping Tests ────────────────────────────────────────

    public function test_score_cannot_exceed_100(): void
    {
        $user = $this->createUser(98.00);

        $this->service->onKycApproved($user); // +10

        $this->assertEquals(100.00, (float) $user->fresh()->trust_score);
    }

    public function test_score_cannot_go_below_0(): void
    {
        $user = $this->createUser(5.00);

        $this->service->onLoanDefault($user, 1); // -15

        $this->assertEquals(0.00, (float) $user->fresh()->trust_score);
    }

    // ─── Tier Tests ──────────────────────────────────────────────────

    public function test_tier_is_bronze_below_50(): void
    {
        $this->assertEquals('bronze', TrustScoreService::getTier(30.00));
        $this->assertEquals('bronze', TrustScoreService::getTier(0.00));
        $this->assertEquals('bronze', TrustScoreService::getTier(49.99));
    }

    public function test_tier_is_silver_from_50_to_69(): void
    {
        $this->assertEquals('silver', TrustScoreService::getTier(50.00));
        $this->assertEquals('silver', TrustScoreService::getTier(60.00));
        $this->assertEquals('silver', TrustScoreService::getTier(69.99));
    }

    public function test_tier_is_gold_from_70_to_84(): void
    {
        $this->assertEquals('gold', TrustScoreService::getTier(70.00));
        $this->assertEquals('gold', TrustScoreService::getTier(80.00));
        $this->assertEquals('gold', TrustScoreService::getTier(84.99));
    }

    public function test_tier_is_platinum_from_85_up(): void
    {
        $this->assertEquals('platinum', TrustScoreService::getTier(85.00));
        $this->assertEquals('platinum', TrustScoreService::getTier(95.00));
        $this->assertEquals('platinum', TrustScoreService::getTier(100.00));
    }

    // ─── Helper Method Tests ─────────────────────────────────────────

    public function test_can_borrow_returns_true_above_threshold(): void
    {
        $user = $this->createUser(50.00);

        $this->assertTrue($user->canBorrow());
    }

    public function test_can_borrow_returns_false_below_threshold(): void
    {
        $user = $this->createUser(20.00);

        $this->assertFalse($user->canBorrow());
    }

    public function test_can_borrow_returns_false_for_inactive_user(): void
    {
        $user = User::factory()->create([
            'trust_score' => 80.00,
            'status' => 'suspended',
        ]);

        $this->assertFalse($user->canBorrow());
    }

    public function test_max_loan_amount_matches_tier(): void
    {
        $this->assertEquals(5000.00, TrustScoreService::maxLoanAmount(
            $this->createUser(35.00),
        ));
        $this->assertEquals(15000.00, TrustScoreService::maxLoanAmount(
            $this->createUser(55.00),
        ));
        $this->assertEquals(50000.00, TrustScoreService::maxLoanAmount(
            $this->createUser(75.00),
        ));
        $this->assertEquals(100000.00, TrustScoreService::maxLoanAmount(
            $this->createUser(90.00),
        ));
    }

    public function test_max_loan_amount_zero_when_cannot_borrow(): void
    {
        $user = $this->createUser(10.00);

        $this->assertEquals(0.00, $user->maxLoanAmount());
    }

    public function test_risk_level_matches_score_bands(): void
    {
        $this->assertEquals('critical', TrustScoreService::riskLevel($this->createUser(10.00)));
        $this->assertEquals('high', TrustScoreService::riskLevel($this->createUser(30.00)));
        $this->assertEquals('elevated', TrustScoreService::riskLevel($this->createUser(45.00)));
        $this->assertEquals('moderate', TrustScoreService::riskLevel($this->createUser(65.00)));
        $this->assertEquals('low', TrustScoreService::riskLevel($this->createUser(85.00)));
    }

    // ─── User Model Accessors ────────────────────────────────────────

    public function test_user_has_trust_tier_accessor(): void
    {
        $user = $this->createUser(75.00);

        $this->assertEquals('gold', $user->trust_tier);
    }

    public function test_user_has_risk_level_accessor(): void
    {
        $user = $this->createUser(65.00);

        $this->assertEquals('moderate', $user->risk_level);
    }

    public function test_user_has_max_loan_amount_accessor(): void
    {
        $user = $this->createUser(90.00);

        $this->assertEquals(100000.00, $user->max_loan_amount);
    }

    // ─── History Tests ───────────────────────────────────────────────

    public function test_score_changes_are_tracked_in_history(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onRepaymentMade($user, 500, 1);
        $this->service->onRepaymentMade($user->fresh(), 500, 1);
        $this->service->onRepaymentOverdue($user->fresh(), 3, 2);

        $history = TrustScoreHistory::forUser($user->id)->get();

        $this->assertCount(3, $history);
        $this->assertEquals(2, $history->where('change', '>', 0)->count());
        $this->assertEquals(1, $history->where('change', '<', 0)->count());
    }

    public function test_history_records_metadata(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onRepaymentMade($user, 1500.00, 42);

        $record = TrustScoreHistory::forUser($user->id)->first();

        $this->assertEquals(42, $record->metadata['loan_id']);
        $this->assertEquals(1500.00, $record->metadata['amount']);
    }

    public function test_user_trust_score_histories_relationship(): void
    {
        $user = $this->createUser(50.00);

        $this->service->onRepaymentMade($user, 500, 1);

        $this->assertCount(1, $user->fresh()->trustScoreHistories);
    }

    // ─── Score Summary Tests ─────────────────────────────────────────

    public function test_get_score_summary_returns_complete_data(): void
    {
        $user = $this->createUser(72.00);
        $this->service->onRepaymentMade($user, 500, 1); // positive event
        $this->service->onRepaymentOverdue($user->fresh(), 2, 2); // negative event

        $summary = $this->service->getScoreSummary($user->fresh());

        $this->assertArrayHasKey('current_score', $summary);
        $this->assertArrayHasKey('tier', $summary);
        $this->assertArrayHasKey('risk_level', $summary);
        $this->assertArrayHasKey('can_borrow', $summary);
        $this->assertArrayHasKey('max_loan_amount', $summary);
        $this->assertArrayHasKey('total_positive_events', $summary);
        $this->assertArrayHasKey('total_negative_events', $summary);
        $this->assertEquals(1, $summary['total_positive_events']);
        $this->assertEquals(1, $summary['total_negative_events']);
    }

    // ─── API Endpoint Tests ──────────────────────────────────────────

    public function test_authenticated_user_can_view_own_score(): void
    {
        $user = $this->createUser(65.00);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/trust-score/my-score');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tier', 'silver')
            ->assertJsonPath('data.can_borrow', true);

        $this->assertEquals(65.00, (float) $response->json('data.current_score'));
    }

    public function test_authenticated_user_can_view_own_history(): void
    {
        $user = $this->createUser(50.00);
        $this->service->onRepaymentMade($user, 500, 1);

        Sanctum::actingAs($user->fresh());

        $response = $this->getJson('/api/trust-score/my-history');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_admin_can_view_any_user_score(): void
    {
        $admin = User::factory()->active()->create();
        $admin->assignRole('admin');
        $target = $this->createUser(80.00);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/trust-score/users/{$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.tier', 'gold');
    }

    public function test_non_admin_cannot_view_other_user_score(): void
    {
        $borrower = $this->createUser(50.00);
        $other = $this->createUser(80.00);

        Sanctum::actingAs($borrower);

        $this->getJson("/api/trust-score/users/{$other->id}")->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_trust_score(): void
    {
        $this->getJson('/api/trust-score/my-score')->assertStatus(401);
    }
}
