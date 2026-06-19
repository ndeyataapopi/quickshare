<?php

namespace Tests\Feature\Funding;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Services\FundingService;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FundingTest extends TestCase
{
    use RefreshDatabase;

    protected FundingService $service;
    protected User $lender;
    protected User $borrower;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(FundingService::class);

        $this->lender = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->lender->assignRole('lender');

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->borrower->assignRole('borrower');
    }

    protected function createMarketplaceLoan(array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15.00,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'funded_amount' => 0,
            'loan_term_days' => 60,
            'status' => 'marketplace',
            'risk_score' => 65.00,
            'submitted_at' => now(),
            'approved_at' => now(),
        ], $overrides));
    }

    // ─── Basic Funding Tests ─────────────────────────────────────────

    public function test_lender_can_fund_loan(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 5000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction.status', 'pending')
            ->assertJsonPath('data.loan_status', 'partially_funded');
        
        // Amount may be returned as string '5000.00' due to decimal cast
        $amount = $response->json('data.transaction.amount');
        $this->assertEquals(5000, (float) $amount);
        $this->assertEquals(5000, (float) $response->json('data.remaining_funding'));

        $this->assertDatabaseHas('funding_transactions', [
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'status' => 'pending',
        ]);
    }

    public function test_funding_creates_transaction_reference(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 5000,
        ]);

        $reference = $response->json('data.transaction.transaction_reference');
        $this->assertNotNull($reference);
        $this->assertStringStartsWith('FUND-', $reference);
    }

    public function test_multiple_lenders_can_fund_same_loan(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan();

        $lender2 = User::factory()->active()->create();
        $lender2->assignRole('lender');

        Sanctum::actingAs($this->lender);
        $this->postJson("/api/funding/{$loan->id}", ['amount' => 3000])->assertOk();

        Sanctum::actingAs($lender2);
        $response = $this->postJson("/api/funding/{$loan->id}", ['amount' => 4000]);
        $response->assertOk();

        $loan->refresh();
        $this->assertEquals(7000, $loan->funded_amount);
        $this->assertEquals('partially_funded', $loan->status);
    }

    public function test_funding_completes_when_fully_funded(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan(['approved_amount' => 5000]);

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 5000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.loan_status', 'funded')
            ->assertJsonPath('data.remaining_funding', 0);

        $loan->refresh();
        $this->assertEquals('funded', $loan->status);
    }

    // ─── Overfunding Protection ──────────────────────────────────────

    public function test_cannot_fund_more_than_remaining(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan(['approved_amount' => 5000]);

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 6000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_fund_already_funded_loan(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan(['status' => 'funded', 'funded_amount' => 10000]);

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This loan is not available for funding.');
    }

    // ─── Single Lender Per Loan ─────────────────────────────────────

    public function test_lender_cannot_fund_same_loan_twice(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);
        $this->postJson("/api/funding/{$loan->id}", ['amount' => 3000])->assertOk();

        $response = $this->postJson("/api/funding/{$loan->id}", ['amount' => 2000]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'You have already funded this loan.');
    }

    // ─── Validation Tests ───────────────────────────────────────────

    public function test_funding_requires_minimum_amount(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 50, // below min
        ]);

        $response->assertStatus(422);
    }

    public function test_funding_amount_must_be_positive(): void
    {
        Queue::fake();
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => -100,
        ]);

        $response->assertStatus(422);
    }

    // ─── Cancel Funding Tests ────────────────────────────────────────

    public function test_lender_can_cancel_pending_funding(): void
    {
        Queue::fake(); // Prevent job from auto-confirming
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);
        $fundResponse = $this->postJson("/api/funding/{$loan->id}", ['amount' => 5000]);
        $transactionId = $fundResponse->json('data.transaction.id');

        $cancelResponse = $this->postJson("/api/funding/{$transactionId}/cancel");

        $cancelResponse->assertOk()
            ->assertJsonPath('data.transaction.status', 'cancelled')
            ->assertJsonPath('data.loan_status', 'marketplace');

        $loan->refresh();
        $this->assertEquals(0, $loan->funded_amount);
        $this->assertEquals('marketplace', $loan->status);
    }

    public function test_cannot_cancel_confirmed_funding(): void
    {
        $loan = $this->createMarketplaceLoan();
        $transaction = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$transaction->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only pending transactions can be cancelled.');
    }

    // ─── Portfolio Tests ─────────────────────────────────────────────

    public function test_lender_can_view_portfolio(): void
    {
        $loan1 = $this->createMarketplaceLoan();
        $loan2 = $this->createMarketplaceLoan();

        FundingTransaction::create([
            'loan_id' => $loan1->id,
            'lender_id' => $this->lender->id,
            'amount' => 3000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
            'confirmed_at' => now(),
        ]);

        FundingTransaction::create([
            'loan_id' => $loan2->id,
            'lender_id' => $this->lender->id,
            'amount' => 4000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
            'confirmed_at' => now(),
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/funding');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_lender_can_view_portfolio_summary(): void
    {
        $loan = $this->createMarketplaceLoan(['status' => 'active']);

        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'expected_return' => 5123,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
            'confirmed_at' => now(),
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/funding/portfolio/summary');

        $response->assertOk()
            ->assertJsonPath('data.total_invested', 5000)
            ->assertJsonPath('data.total_expected_return', 5123)
            ->assertJsonPath('data.active_investments', 1);
    }

    // ─── View Funding Details ────────────────────────────────────────

    public function test_lender_can_view_own_funding_transaction(): void
    {
        $loan = $this->createMarketplaceLoan();
        $transaction = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson("/api/funding/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('data.transaction.id', $transaction->id);
        
        // Amount may be returned as string '5000.00' due to decimal cast
        $amount = $response->json('data.transaction.amount');
        $this->assertEquals(5000, (float) $amount);
    }

    public function test_lender_cannot_view_other_lender_transaction(): void
    {
        $loan = $this->createMarketplaceLoan();
        $lender2 = User::factory()->active()->create();
        $lender2->assignRole('lender');

        $transaction = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender2->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson("/api/funding/{$transaction->id}");

        $response->assertStatus(404);
    }

    // ─── Loan Funding Details (Public) ──────────────────────────────

    public function test_anyone_can_view_loan_funding_details(): void
    {
        $loan = $this->createMarketplaceLoan(['funded_amount' => 6000]);

        $lender2 = User::factory()->active()->create();
        $lender2->assignRole('lender');
        $lender3 = User::factory()->active()->create();
        $lender3->assignRole('lender');

        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 3000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender2->id,
            'amount' => 3000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        Sanctum::actingAs($lender3); // Different lender viewing

        $response = $this->getJson("/api/funding/loan/{$loan->id}/fundings");

        $response->assertOk()
            ->assertJsonCount(2, 'data.fundings')
            ->assertJsonPath('data.summary.lender_count', 2)
            ->assertJsonPath('data.summary.funded_amount', 6000);

        // Lenders should be anonymized
        $fundings = $response->json('data.fundings');
        $this->assertArrayNotHasKey('lender_id', $fundings[0]);
        $this->assertArrayHasKey('lender_hash', $fundings[0]);
    }

    // ─── Service Tests ───────────────────────────────────────────────

    public function test_service_calculates_remaining_funding(): void
    {
        $loan = $this->createMarketplaceLoan(['approved_amount' => 10000, 'funded_amount' => 3000]);

        $remaining = $this->service->getRemainingFunding($loan);

        $this->assertEquals(7000, $remaining);
    }

    public function test_service_returns_loan_funding_summary(): void
    {
        $loan = $this->createMarketplaceLoan(['approved_amount' => 10000, 'funded_amount' => 6000]);

        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 3000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $lender2 = User::factory()->active()->create();
        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender2->id,
            'amount' => 3000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $summary = $this->service->getLoanFundingSummary($loan->id);

        $this->assertEquals(10000, $summary['target_amount']);
        $this->assertEquals(6000, $summary['funded_amount']);
        $this->assertEquals(4000, $summary['remaining_amount']);
        $this->assertEquals(60.00, $summary['progress_percent']);
        $this->assertEquals(2, $summary['lender_count']);
    }

    public function test_expected_return_calculation(): void
    {
        $loan = $this->createMarketplaceLoan([
            'approved_amount' => 10000,
            'interest_rate' => 15.00,
            'loan_term_days' => 60,
        ]);

        Sanctum::actingAs($this->lender);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 5000,
        ]);

        $response->assertOk();
        $expectedReturn = $response->json('data.transaction.expected_return');
        
        // 5000 + (5000 * 0.15 * 60/365) = 5000 + 123.29 ≈ 5123.29
        $this->assertGreaterThan(5000, $expectedReturn);
    }

    // ─── RBAC Tests ──────────────────────────────────────────────────

    public function test_borrower_cannot_fund_loan(): void
    {
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 5000,
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_fund(): void
    {
        $loan = $this->createMarketplaceLoan();

        $response = $this->postJson("/api/funding/{$loan->id}", [
            'amount' => 5000,
        ]);

        $response->assertStatus(401);
    }

    // ─── Model Tests ─────────────────────────────────────────────────

    public function test_transaction_reference_is_unique(): void
    {
        $loan = $this->createMarketplaceLoan();

        $ref1 = FundingTransaction::generateReference();
        $ref2 = FundingTransaction::generateReference();

        $this->assertNotEquals($ref1, $ref2);
        $this->assertStringStartsWith('FUND-', $ref1);
        $this->assertEquals(17, strlen($ref1)); // FUND- + 12 hex chars
    }

    public function test_user_has_funding_transactions_relationship(): void
    {
        $loan = $this->createMarketplaceLoan();
        
        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $this->assertCount(1, $this->lender->fresh()->fundingTransactions);
    }

    public function test_loan_has_funding_transactions_relationship(): void
    {
        $loan = $this->createMarketplaceLoan();
        
        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $this->assertCount(1, $loan->fresh()->fundingTransactions);
    }
}
