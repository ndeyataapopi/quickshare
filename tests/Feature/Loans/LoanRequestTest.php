<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\Loans\DTOs\LoanCalculation;
use App\Modules\Loans\DTOs\LoanRequestData;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $borrower;
    protected User $admin;
    protected LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->loanService = app(LoanService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 55.00]);
        $this->borrower->assignRole('borrower');

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->admin->assignRole('admin');
    }

    // ─── Loan Calculation Tests ──────────────────────────────────────

    public function test_loan_calculation_returns_correct_values(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 10000.00, 90);

        $this->assertEquals(10000.00, $calc->principal);
        $this->assertEquals(15.00, $calc->interestRate);
        $this->assertEquals(90, $calc->termDays);
        $this->assertGreaterThan(0, $calc->interestAmount);
        $this->assertGreaterThan(0, $calc->platformFee);
        $this->assertGreaterThan($calc->principal, $calc->totalRepayment);
        $this->assertEquals('silver', $calc->trustTier);
    }

    public function test_interest_calculation_is_proportional_to_term(): void
    {
        $calc30 = $this->loanService->calculate($this->borrower, 10000.00, 30);
        $calc90 = $this->loanService->calculate($this->borrower, 10000.00, 90);

        $this->assertGreaterThan($calc30->interestAmount, $calc90->interestAmount);
        // 90-day interest should be approx 3x the 30-day interest
        $ratio = $calc90->interestAmount / $calc30->interestAmount;
        $this->assertEqualsWithDelta(3.0, $ratio, 0.01);
    }

    public function test_platform_fee_is_percentage_of_principal(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 10000.00, 30);
        $expectedFee = round(10000 * (config('loans.platform_fee_percent') / 100), 2);

        $this->assertEquals($expectedFee, $calc->platformFee);
    }

    public function test_total_repayment_equals_principal_plus_interest_plus_fee(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 10000.00, 60);

        $expected = round($calc->principal + $calc->interestAmount + $calc->platformFee, 2);
        $this->assertEquals($expected, $calc->totalRepayment);
    }

    public function test_calculation_dto_to_array(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 5000.00, 30);
        $arr = $calc->toArray();

        $this->assertArrayHasKey('principal', $arr);
        $this->assertArrayHasKey('interest_rate', $arr);
        $this->assertArrayHasKey('interest_amount', $arr);
        $this->assertArrayHasKey('platform_fee', $arr);
        $this->assertArrayHasKey('total_repayment', $arr);
        $this->assertArrayHasKey('risk_score', $arr);
        $this->assertArrayHasKey('risk_level', $arr);
        $this->assertArrayHasKey('trust_tier', $arr);
        $this->assertArrayHasKey('max_allowed_amount', $arr);
    }

    // ─── Loan Request API Tests ──────────────────────────────────────

    public function test_borrower_can_request_loan(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 5000.00,
            'loan_term_days' => 60,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loan.status', 'pending_review')
            ->assertJsonStructure([
                'data' => [
                    'loan' => [
                        'id', 'reference', 'requested_amount', 'interest_rate',
                        'platform_fee', 'total_repayment', 'loan_term_days',
                        'risk_score', 'status',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('loans', [
            'borrower_id' => $this->borrower->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_loan_request_generates_unique_reference(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => 5000.00,
            'loan_term_days' => 60,
        ]);

        $loan = Loan::first();
        $this->assertStringStartsWith('QS-', $loan->reference);
        $this->assertEquals(15, strlen($loan->reference)); // QS- + 12 hex chars
    }

    public function test_loan_request_fails_below_minimum_amount(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 100.00,
            'loan_term_days' => 60,
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_above_trust_tier_limit(): void
    {
        // Silver tier max is 15000
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 20000.00,
            'loan_term_days' => 60,
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_with_invalid_term(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => 5000.00,
            'loan_term_days' => 10, // below 30 min
        ])->assertStatus(422);

        $this->postJson('/api/loans/request', [
            'requested_amount' => 5000.00,
            'loan_term_days' => 500, // above 365 max
        ])->assertStatus(422);
    }

    public function test_loan_request_fails_for_low_trust_score(): void
    {
        $lowTrustUser = User::factory()->active()->create(['trust_score' => 20.00]);
        $lowTrustUser->assignRole('borrower');

        Sanctum::actingAs($lowTrustUser);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 1000.00,
            'loan_term_days' => 30,
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_for_inactive_user(): void
    {
        $inactive = User::factory()->create(['trust_score' => 60.00, 'status' => 'suspended']);
        $inactive->assignRole('borrower');

        Sanctum::actingAs($inactive);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 5000.00,
            'loan_term_days' => 60,
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_when_max_active_loans_reached(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Loan::create([
                'borrower_id' => $this->borrower->id,
                'reference' => Loan::generateReference(),
                'requested_amount' => 1000,
                'interest_rate' => 15,
                'platform_fee' => 30,
                'total_repayment' => 1067,
                'loan_term_days' => 30,
                'status' => 'active',
                'submitted_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 1000.00,
            'loan_term_days' => 30,
        ]);

        $response->assertStatus(422);
    }

    // ─── Calculation API Tests ───────────────────────────────────────

    public function test_borrower_can_calculate_loan(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/calculate', [
            'amount' => 10000.00,
            'term_days' => 90,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'principal', 'interest_rate', 'term_days',
                    'interest_amount', 'platform_fee', 'total_repayment',
                    'risk_score', 'risk_level', 'trust_tier', 'max_allowed_amount',
                ],
            ]);
    }

    // ─── Borrower Loan List Tests ────────────────────────────────────

    public function test_borrower_can_view_own_loans(): void
    {
        Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson('/api/loans');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_borrower_cannot_view_other_users_loan(): void
    {
        $otherBorrower = User::factory()->active()->create(['trust_score' => 55.00]);
        $otherBorrower->assignRole('borrower');

        $loan = Loan::create([
            'borrower_id' => $otherBorrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $this->getJson("/api/loans/{$loan->id}")->assertStatus(403);
    }

    // ─── Cancel Tests ────────────────────────────────────────────────

    public function test_borrower_can_cancel_draft_or_pending_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson("/api/loans/{$loan->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.loan.status', 'cancelled');
    }

    public function test_borrower_cannot_cancel_active_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'active',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $this->postJson("/api/loans/{$loan->id}/cancel")->assertStatus(422);
    }

    // ─── Admin Review Tests ──────────────────────────────────────────

    public function test_admin_can_approve_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/loans/admin/{$loan->id}/approve", [
            'admin_notes' => 'Looks good.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.loan.status', 'marketplace');

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'marketplace',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_approve_with_different_amount(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/loans/admin/{$loan->id}/approve", [
            'approved_amount' => 8000.00,
        ]);

        $response->assertStatus(200);

        $approvedLoan = Loan::find($loan->id);
        $this->assertEquals(8000.00, (float) $approvedLoan->approved_amount);
        $this->assertNotNull($approvedLoan->repayment_date);
    }

    public function test_admin_can_reject_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/loans/admin/{$loan->id}/reject", [
            'reason' => 'Insufficient documentation.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.loan.status', 'cancelled');

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'rejection_reason' => 'Insufficient documentation.',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/loans/admin/{$loan->id}/reject", [])->assertStatus(422);
    }

    public function test_cannot_approve_already_approved_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/loans/admin/{$loan->id}/approve")->assertStatus(422);
    }

    // ─── Admin Listing Tests ─────────────────────────────────────────

    public function test_admin_can_view_pending_loans(): void
    {
        Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/loans/admin/pending');
        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    // ─── RBAC Tests ──────────────────────────────────────────────────

    public function test_borrower_cannot_access_admin_loan_routes(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->getJson('/api/loans/admin/pending')->assertStatus(403);
    }

    public function test_lender_cannot_request_loans(): void
    {
        $lender = User::factory()->active()->create(['trust_score' => 60.00]);
        $lender->assignRole('lender');

        Sanctum::actingAs($lender);

        $this->postJson('/api/loans/request', [
            'requested_amount' => 5000,
            'loan_term_days' => 60,
        ])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_loan_routes(): void
    {
        $this->getJson('/api/loans')->assertStatus(401);
        $this->postJson('/api/loans/request')->assertStatus(401);
        $this->getJson('/api/loans/admin/pending')->assertStatus(401);
    }

    // ─── DTO Tests ───────────────────────────────────────────────────

    public function test_loan_request_data_from_array(): void
    {
        $dto = LoanRequestData::fromArray([
            'borrower_id' => 1,
            'requested_amount' => 5000,
            'loan_term_days' => 60,
            'purpose' => 'Home repairs',
        ]);

        $this->assertEquals(1, $dto->borrowerId);
        $this->assertEquals(5000.00, $dto->requestedAmount);
        $this->assertEquals(60, $dto->loanTermDays);
        $this->assertEquals('Home repairs', $dto->purpose);
    }

    public function test_loan_request_data_to_array(): void
    {
        $dto = new LoanRequestData(
            borrowerId: 1,
            requestedAmount: 5000.00,
            loanTermDays: 60,
        );

        $arr = $dto->toArray();

        $this->assertEquals(1, $arr['borrower_id']);
        $this->assertEquals(5000.00, $arr['requested_amount']);
        $this->assertEquals(60, $arr['loan_term_days']);
        $this->assertNull($arr['purpose']);
    }

    // ─── Model Tests ─────────────────────────────────────────────────

    public function test_loan_funding_progress_calculation(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'funded_amount' => 6000,
            'loan_term_days' => 60,
            'status' => 'partially_funded',
            'submitted_at' => now(),
        ]);

        $this->assertEquals(60.00, $loan->funding_progress);
        $this->assertEquals(4000.00, $loan->remaining_funding);
    }

    public function test_loan_status_helper_methods(): void
    {
        $loan = new Loan(['status' => 'draft']);
        $this->assertTrue($loan->isDraft());
        $this->assertTrue($loan->isCancellable());

        $loan->status = 'pending_review';
        $this->assertTrue($loan->isPendingReview());
        $this->assertTrue($loan->isApprovable());
        $this->assertTrue($loan->isCancellable());

        $loan->status = 'marketplace';
        $this->assertTrue($loan->isOnMarketplace());
        $this->assertFalse($loan->isCancellable());

        $loan->status = 'funded';
        $this->assertTrue($loan->isFunded());
        $this->assertTrue($loan->isDisbursable());

        $loan->status = 'active';
        $this->assertTrue($loan->isActive());

        $loan->status = 'completed';
        $this->assertTrue($loan->isCompleted());

        $loan->status = 'defaulted';
        $this->assertTrue($loan->isDefaulted());
    }
}
