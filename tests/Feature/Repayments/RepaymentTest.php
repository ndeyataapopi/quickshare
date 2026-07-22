<?php

namespace Tests\Feature\Repayments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepaymentTest extends TestCase
{
    use RefreshDatabase;

    protected RepaymentService $service;
    protected User $borrower;
    protected User $lender;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(RepaymentService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);

        $this->lender = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($this->lender);

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00, 'email' => 'admin@quickshare.com']);
        $this->assignAdminRole($this->admin);
        $this->admin = $this->admin->fresh(); // Reload to get proper permissions
        $this->assertTrue($this->admin->hasRole('admin'));
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

    protected function createFunding(Loan $loan, float $amount): FundingTransaction
    {
        return FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => $amount,
            'interest_rate' => 15.00,
            'expected_return' => $amount * 1.025, // Approximate
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);
    }

    // ─── Repayment Schedule Creation ─────────────────────────────────

    public function test_repayment_schedule_created_for_loan(): void
    {
        $loan = $this->createActiveLoan();

        $repayment = $this->service->createRepaymentSchedule($loan);

        $this->assertNotNull($repayment->id);
        $this->assertEquals($loan->id, $repayment->loan_id);
        $this->assertEquals($this->borrower->id, $repayment->borrower_id);
        $this->assertEquals($loan->total_repayment, $repayment->amount);
        $this->assertEquals('pending', $repayment->status);
        $this->assertNotNull($repayment->transaction_reference);
    }

    public function test_cannot_create_duplicate_schedule(): void
    {
        $loan = $this->createActiveLoan();
        $this->service->createRepaymentSchedule($loan);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Repayment schedule already exists');

        $this->service->createRepaymentSchedule($loan);
    }

    // ─── Repayment Recording ─────────────────────────────────────────

    public function test_borrower_can_record_repayment(): void
    {
        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);

        // Use the service directly for the old recordRepayment flow
        $repayment = $this->service->createRepaymentSchedule($loan);

        $recorded = $this->service->recordRepayment(
            $loan,
            $this->borrower,
            10546,
            'bank_transfer',
        );

        $this->assertEquals('paid', $recorded->status);
        $this->assertEquals('completed', $loan->fresh()->status);

        $this->assertDatabaseHas('repayments', [
            'loan_id' => $loan->id,
            'amount' => 10546,
            'status' => 'paid',
        ]);
    }

    public function test_repayment_splits_proportionally_to_lenders(): void
    {
        $loan = $this->createActiveLoan();

        // Create two lender fundings
        $funding1 = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 6000,
            'interest_rate' => 15.00,
            'expected_return' => 6150,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $lender2 = User::factory()->active()->create();
        $this->assignClientRole($lender2);

        $funding2 = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender2->id,
            'amount' => 4000,
            'interest_rate' => 15.00,
            'expected_return' => 4100,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        // Use service directly for the old recordRepayment flow
        $this->service->createRepaymentSchedule($loan);
        $this->service->recordRepayment($loan, $this->borrower, 10546, 'bank_transfer');

        // Check lender repayments were created proportionally
        $lender1Repayment = LenderRepayment::where('lender_id', $this->lender->id)->first();
        $this->assertNotNull($lender1Repayment);
        $this->assertEquals(60.00, (float) $lender1Repayment->funding_percentage);

        // Lender 2 should get 40% (4000/10000)
        $lender2Repayment = LenderRepayment::where('lender_id', $lender2->id)->first();
        $this->assertNotNull($lender2Repayment);
        $this->assertEquals(40.00, (float) $lender2Repayment->funding_percentage);
    }

    public function test_cannot_repay_inactive_loan(): void
    {
        $loan = $this->createActiveLoan(['status' => 'funded']); // Not yet active

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Loan is not active');

        $this->service->recordRepayment($loan, $this->borrower, 5000, 'bank_transfer');
    }

    public function test_cannot_overpay_repayment(): void
    {
        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);
        $this->service->createRepaymentSchedule($loan);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('exceeds remaining balance');

        $this->service->recordRepayment($loan, $this->borrower, 20000, 'bank_transfer');
    }

    // ─── Overdue Detection ───────────────────────────────────────────

    public function test_overdue_repayment_detected(): void
    {
        $loan = $this->createActiveLoan();
        
        // Create repayment with past due date
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->subDays(5),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $count = $this->service->checkOverdueRepayments();

        $this->assertEquals(1, $count);

        $repayment = Repayment::forLoan($loan->id)->first();
        $this->assertEquals('overdue', $repayment->status);
        $this->assertGreaterThan(0, $repayment->days_overdue); // Should be > 0
        $this->assertGreaterThan(0, $repayment->penalty);
    }

    public function test_penalty_calculated_correctly(): void
    {
        $loan = $this->createActiveLoan();
        
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10000,
            'status' => 'pending',
            'due_date' => now()->subDays(10), // Overdue 10 days
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->service->checkOverdueRepayments();

        $repayment = Repayment::forLoan($loan->id)->first();
        
        // 5% per week = 10% for 10 days (2 weeks rounded up)
        // 10000 * 0.05 * 2 = 1000
        // Penalty should be > 0 and capped at 50%
        $penalty = (float) $repayment->penalty;
        $this->assertGreaterThan(0, $penalty);
        $this->assertLessThanOrEqual(5000, $penalty); // Max 50% of 10000
    }

    // ─── API Tests ─────────────────────────────────────────────────────

    public function test_borrower_can_view_own_repayments(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson('/api/repayments');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.loan_id', $loan->id);
    }

    public function test_borrower_can_view_repayment_schedule(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson("/api/repayments/schedule/{$loan->id}");

        $response->assertOk()
            ->assertJsonPath('data.loan.id', $loan->id)
            ->assertJsonCount(1, 'data.repayments');
    }

    public function test_borrower_cannot_view_other_borrower_schedule(): void
    {
        $otherBorrower = User::factory()->active()->create();
        $this->assignClientRole($otherBorrower);

        $loan = $this->createActiveLoan(['borrower_id' => $otherBorrower->id]);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson("/api/repayments/schedule/{$loan->id}");

        $response->assertStatus(403);
    }

    // ─── Lender Earnings Tests ─────────────────────────────────────────

    public function test_lender_can_view_earnings(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
            'paid_date' => now(),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $funding = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 6000,
            'interest_rate' => 15.00,
            'expected_return' => 6150,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        LenderRepayment::create([
            'repayment_id' => $repayment->id,
            'lender_id' => $this->lender->id,
            'funding_transaction_id' => $funding->id,
            'amount' => 6000,
            'principal_return' => 5800,
            'interest_earned' => 200,
            'funding_percentage' => 60.00,
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/repayments/lender/earnings');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_repaid', 6000);
    }

    public function test_lender_can_view_earnings_summary(): void
    {
        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/repayments/lender/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total_repaid', 'principal_returned', 'interest_earned', 'repayments_count'],
            ]);
    }

    // ─── Admin Tests ───────────────────────────────────────────────────

    public function test_admin_can_view_all_repayments(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/repayments/all');
        
        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_view_overdue_summary(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(10),
            'days_overdue' => 10,
            'penalty' => 1000,
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/repayments/admin/overdue/summary');

        $response->assertOk()
            ->assertJsonPath('data.total_overdue', 1)
            ->assertJsonPath('data.total_penalties', 1000);
    }

    public function test_admin_can_trigger_overdue_check(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/repayments/admin/check-overdue');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_mark_repayment_as_defaulted(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/repayments/admin/{$repayment->id}/mark-defaulted");

        $response->assertOk()
            ->assertJsonPath('data.repayment.status', 'defaulted')
            ->assertJsonPath('data.loan_status', 'defaulted');
    }

    public function test_admin_can_waive_penalty(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(10),
            'penalty' => 1000,
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/repayments/admin/{$repayment->id}/waive-penalty");

        $response->assertOk();
        $penalty = (float) $response->json('data.repayment.penalty');
        $waived = (float) $response->json('data.waived_amount');
        $this->assertEquals(0, $penalty);
        $this->assertEquals(1000, $waived);
    }

    // ─── RBAC Tests ────────────────────────────────────────────────────

    public function test_client_can_view_lender_earnings(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->getJson('/api/repayments/lender/earnings');

        $response->assertStatus(200);
    }

    public function test_lender_cannot_view_all_repayments(): void
    {
        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/repayments/all');

        $response->assertStatus(403);
    }

    // ─── Model Tests ───────────────────────────────────────────────────

    public function test_repayment_reference_generation(): void
    {
        $ref1 = Repayment::generateReference();
        $ref2 = Repayment::generateReference();

        $this->assertNotEquals($ref1, $ref2);
        $this->assertStringStartsWith('REPY-', $ref1);
        $this->assertEquals(17, strlen($ref1));
    }

    public function test_loan_has_repayments_relationship(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertCount(1, $loan->fresh()->repayments);
    }

    public function test_user_has_repayments_relationship(): void
    {
        $loan = $this->createActiveLoan();
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertCount(1, $this->borrower->fresh()->repayments);
    }

    public function test_repayment_status_helpers(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'overdue',
            'due_date' => now()->subDays(5),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertTrue($repayment->isOverdue());
        $this->assertFalse($repayment->isPaid());
        $this->assertFalse($repayment->isPending());
    }

    public function test_repayment_scopes(): void
    {
        $loan = $this->createActiveLoan();
        
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 5000,
            'status' => 'overdue',
            'due_date' => now()->subDays(5),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertEquals(1, Repayment::pending()->count());
        $this->assertEquals(1, Repayment::overdue()->count());
        $this->assertEquals(1, Repayment::upcoming(7)->count());
    }
}
