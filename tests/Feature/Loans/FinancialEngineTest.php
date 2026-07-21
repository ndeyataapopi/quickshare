<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialEngineTest extends TestCase
{
    use RefreshDatabase;

    protected LoanService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(LoanService::class);
    }

    protected function borrower(float $trustScore): User
    {
        return User::factory()->active()->create(['trust_score' => $trustScore]);
    }

    protected function marketplaceLoan(array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'borrower_id' => $this->borrower(30.00)->id,
            'reference' => Loan::generateReference(),
            'approved_amount' => 1000.00,
            'requested_amount' => 1000.00,
            'interest_rate' => 30.00,
            'platform_fee' => 50.00,
            'total_repayment' => 1300.00,
            'funded_amount' => 0.00,
            'loan_term_days' => 30,
            'risk_score' => 30.00,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ], $overrides));
    }

    public function test_loan_calculation_uses_flat_fee(): void
    {
        $borrower = $this->borrower(30.00);

        $calc = $this->engine->calculate($borrower, 1000.00, 30);

        $this->assertEquals(50.00, $calc->platformFee);
        $this->assertEquals(250.00, $calc->lenderReturnAmount);
        $this->assertEquals(300.00, $calc->interestAmount);
        $this->assertEquals(1300.00, $calc->totalRepayment);
        $this->assertEquals(30.00, $calc->interestRate);
        $this->assertEquals('bronze', $calc->trustTier);
        $this->assertEquals('high', $calc->riskLevel);
    }

    public function test_loan_interest_is_independent_of_term(): void
    {
        $borrower = $this->borrower(30.00);

        $short = $this->engine->calculate($borrower, 1000.00, 7);
        $long = $this->engine->calculate($borrower, 1000.00, 30);

        $this->assertEquals($short->interestAmount, $long->interestAmount);
        $this->assertEquals($short->totalRepayment, $long->totalRepayment);
        $this->assertNotEquals($short->repaymentDate, $long->repaymentDate);
    }

    public function test_tier_total_charge_percent_is_sum_of_fees(): void
    {
        $platformFeePercent = $this->engine->platformFeePercentForScore(30.00);
        $lenderReturnPercent = $this->engine->lenderReturnPercentForScore(30.00);
        $totalChargePercent = $this->engine->totalChargePercentForScore(30.00);

        $this->assertEquals($platformFeePercent + $lenderReturnPercent, $totalChargePercent);
    }

    public function test_funding_expected_return_for_single_lender(): void
    {
        $loan = $this->marketplaceLoan();

        $calc = $this->engine->fundingCalculation($loan, 500.00);

        $this->assertEquals(500.00, $calc['investment_amount']);
        $this->assertEquals(250.00, $calc['lender_return_pool']);
        $this->assertEquals(125.00, $calc['expected_profit']);
        $this->assertEquals(625.00, $calc['expected_return']);
        $this->assertEquals(50.00, $calc['funding_percentage']);
    }

    public function test_multiple_lenders_receive_proportional_returns(): void
    {
        $loan = $this->marketplaceLoan();

        $lenders = [500.00, 300.00, 200.00];
        $expectedReturns = [625.00, 375.00, 250.00];
        $expectedProfits = [125.00, 75.00, 50.00];

        foreach ($lenders as $index => $amount) {
            $calc = $this->engine->fundingCalculation($loan, $amount);

            $this->assertEquals($expectedReturns[$index], $calc['expected_return']);
            $this->assertEquals($expectedProfits[$index], $calc['expected_profit']);
        }
    }

    public function test_funding_progress_and_remaining_funding(): void
    {
        $loan = $this->marketplaceLoan();

        $this->assertEquals(0.00, $this->engine->fundingProgress($loan));
        $this->assertEquals(1000.00, $this->engine->remainingFunding($loan));

        $loan->update(['funded_amount' => 400.00]);

        $this->assertEquals(40.00, $this->engine->fundingProgress($loan));
        $this->assertEquals(600.00, $this->engine->remainingFunding($loan));
    }

    public function test_repayment_calculation_matches_loan_values(): void
    {
        $loan = $this->marketplaceLoan();

        $repayment = $this->engine->repaymentCalculation($loan);

        $this->assertEquals(1300.00, $repayment['amount']);
        $this->assertEquals(1000.00, $repayment['principal']);
        $this->assertEquals(50.00, $repayment['platform_fee']);
        $this->assertEquals(250.00, $repayment['lender_return']);
    }

    public function test_lender_repayment_distribution_excludes_platform_fee(): void
    {
        $loan = $this->marketplaceLoan();
        $borrower = User::factory()->active()->create();
        $lenderA = User::factory()->active()->create();
        $lenderB = User::factory()->active()->create();

        $fundingA = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lenderA->id,
            'amount' => 500.00,
            'interest_rate' => 25.00,
            'expected_return' => 625.00,
            'status' => 'confirmed',
            'transaction_reference' => 'FUND-A',
        ]);

        $fundingB = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lenderB->id,
            'amount' => 500.00,
            'interest_rate' => 25.00,
            'expected_return' => 625.00,
            'status' => 'confirmed',
            'transaction_reference' => 'FUND-B',
        ]);

        $distA = $this->engine->lenderRepaymentDistribution($loan, $fundingA, 1000.00, 1300.00);
        $distB = $this->engine->lenderRepaymentDistribution($loan, $fundingB, 1000.00, 1300.00);

        $this->assertEquals(625.00, $distA['amount']);
        $this->assertEquals(500.00, $distA['principal_return']);
        $this->assertEquals(125.00, $distA['interest_earned']);

        $this->assertEquals(625.00, $distB['amount']);
        $this->assertEquals(500.00, $distB['principal_return']);
        $this->assertEquals(125.00, $distB['interest_earned']);

        $this->assertEquals(1000.00, $distA['principal_return'] + $distB['principal_return']);
        $this->assertEquals(250.00, $distA['interest_earned'] + $distB['interest_earned']);
    }

    public function test_partial_repayment_distribution_is_proportional(): void
    {
        $loan = $this->marketplaceLoan();
        $lender = User::factory()->active()->create();

        $funding = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => 1000.00,
            'interest_rate' => 25.00,
            'expected_return' => 1250.00,
            'status' => 'confirmed',
            'transaction_reference' => 'FUND-ALL',
        ]);

        $dist = $this->engine->lenderRepaymentDistribution($loan, $funding, 1000.00, 650.00);

        // 650 pays half of total repayment; after platform share, lenders get half of principal+return.
        $this->assertEquals(625.00, $dist['amount']);
        $this->assertEquals(500.00, $dist['principal_return']);
        $this->assertEquals(125.00, $dist['interest_earned']);
    }

    public function test_penalty_is_capped_at_config_ratio(): void
    {
        $borrower = $this->borrower(30.00);
        $loan = $this->marketplaceLoan(['borrower_id' => $borrower->id]);
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $borrower->id,
            'amount' => 1300.00,
            'principal' => 1000.00,
            'interest' => 250.00,
            'platform_fee' => 50.00,
            'penalty' => 0,
            'status' => 'pending',
            'due_date' => now()->subDays(14),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $penalty = $this->engine->penaltyForRepayment($repayment, 14);

        $this->assertEquals(130.00, $penalty); // 5% per week * 2 weeks = 10% of 1300
        $this->assertLessThanOrEqual(1300.00 * 0.50, $penalty);
    }

    public function test_affordability_max_loan_uses_flat_total_charge(): void
    {
        $borrower = $this->borrower(55.00); // silver tier

        $maxLoan = $this->engine->affordabilityMaxLoan($borrower, 1000.00);

        // Disposable income 1000, max 30% = 300/month, max term 30 days = 1 month,
        // total charge 30%, multiplier 1.3, max loan = 300 / 1.3 = 230.77
        $this->assertEqualsWithDelta(230.77, $maxLoan, 0.01);
    }

    public function test_disbursement_amount_is_full_principal(): void
    {
        $loan = $this->marketplaceLoan();

        $this->assertEquals(1000.00, $this->engine->disbursementAmount($loan));
    }

    public function test_risk_levels_use_config_thresholds(): void
    {
        $this->assertEquals('high', $this->borrower(30.00)->riskLevel);
        $this->assertEquals('medium', $this->borrower(60.00)->riskLevel);
        $this->assertEquals('low', $this->borrower(85.00)->riskLevel);
    }

    public function test_outstanding_balance_calculation(): void
    {
        $this->assertEquals(300.00, $this->engine->outstandingBalance(1000.00, 700.00));
        $this->assertEquals(0.00, $this->engine->outstandingBalance(1000.00, 1200.00));
        $this->assertEquals(1150.00, $this->engine->outstandingBalance(1000.00, 0.00, 150.00));
    }
}
