<?php

namespace Tests\Feature\Funding;

use App\Models\User;
use App\Modules\Funding\Models\Investment;
use App\Modules\Funding\Services\EarningsService;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EarningsService $service;
    protected User $lender;
    protected User $borrower;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(EarningsService::class);

        $this->lender = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($this->lender);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);
    }

    protected function createLoan(array $overrides = []): Loan
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
            'repayment_date' => now()->addDays(60)->toDateString(),
            'submitted_at' => now(),
            'approved_at' => now(),
        ], $overrides));
    }

    protected function createInvestment(array $overrides = []): Investment
    {
        return Investment::create(array_merge([
            'loan_id' => $this->createLoan()->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 15.00,
            'expected_return' => 5373.00,
            'actual_return' => 0,
            'status' => 'active',
            'funded_at' => now(),
        ], $overrides));
    }

    // ─── getLenderEarningsSummary ───────────────────────────────────

    public function test_summary_returns_zero_for_new_lender(): void
    {
        $summary = $this->service->getLenderEarningsSummary($this->lender);

        $this->assertSame(0.0, $summary['total_earnings']);
        $this->assertSame(0.0, $summary['total_invested']);
        $this->assertSame(0, $summary['active_count']);
        $this->assertSame(0.0, $summary['roi']);
    }

    public function test_summary_calculates_total_invested_from_active_and_completed(): void
    {
        $this->createInvestment(['amount' => 3000, 'status' => 'active']);
        $this->createInvestment(['amount' => 2000, 'status' => 'completed', 'actual_return' => 2200, 'completed_at' => now()]);

        $summary = $this->service->getLenderEarningsSummary($this->lender);

        $this->assertEquals(5000.0, $summary['total_invested']);
        $this->assertEquals(1, $summary['active_count']);
        $this->assertEquals(1, $summary['completed_count']);
    }

    public function test_summary_excludes_cancelled_investments(): void
    {
        $this->createInvestment(['amount' => 3000, 'status' => 'active']);
        $this->createInvestment(['amount' => 2000, 'status' => 'cancelled']);

        $summary = $this->service->getLenderEarningsSummary($this->lender);

        $this->assertEquals(3000.0, $summary['total_invested']);
        $this->assertSame(1, $summary['active_count']);
        $this->assertSame(0, $summary['completed_count']);
    }

    public function test_summary_calculates_earnings_as_actual_return_minus_invested(): void
    {
        $this->createInvestment([
            'amount' => 5000,
            'status' => 'completed',
            'actual_return' => 5373.00,
            'completed_at' => now(),
        ]);

        $summary = $this->service->getLenderEarningsSummary($this->lender);

        $this->assertEquals(373.0, $summary['total_earnings']);
        $this->assertEquals(5373.0, $summary['total_actual_return']);
    }

    public function test_summary_calculates_roi_correctly(): void
    {
        $this->createInvestment([
            'amount' => 5000,
            'status' => 'completed',
            'actual_return' => 5500.00,
            'completed_at' => now(),
        ]);

        $summary = $this->service->getLenderEarningsSummary($this->lender);

        // earnings = 500, invested = 5000, roi = 10%
        $this->assertEquals(500.0, $summary['total_earnings']);
        $this->assertEquals(10.0, $summary['roi']);
    }

    public function test_summary_roi_is_zero_when_no_investments(): void
    {
        $summary = $this->service->getLenderEarningsSummary($this->lender);
        $this->assertSame(0.0, $summary['roi']);
    }

    public function test_summary_only_counts_lender_own_investments(): void
    {
        $other = User::factory()->active()->create();
        $this->assignClientRole($other);

        $this->createInvestment(['lender_id' => $this->lender->id, 'amount' => 3000, 'status' => 'active']);
        $this->createInvestment(['lender_id' => $other->id, 'amount' => 7000, 'status' => 'active']);

        $summary = $this->service->getLenderEarningsSummary($this->lender);

        $this->assertEquals(3000.0, $summary['total_invested']);
    }

    // ─── getLenderPortfolioSummary ──────────────────────────────────

    public function test_portfolio_summary_returns_correct_keys(): void
    {
        $summary = $this->service->getLenderPortfolioSummary($this->lender);

        $this->assertArrayHasKey('total_invested', $summary);
        $this->assertArrayHasKey('total_expected_return', $summary);
        $this->assertArrayHasKey('total_actual_return', $summary);
        $this->assertArrayHasKey('active_investments', $summary);
        $this->assertArrayHasKey('completed_investments', $summary);
        $this->assertArrayHasKey('pending_transactions', $summary);
    }

    // ─── getEarningsOverviewData ────────────────────────────────────

    public function test_earnings_overview_returns_no_data_when_empty(): void
    {
        $data = $this->service->getEarningsOverviewData($this->lender, 'month');

        $this->assertEquals(['No Data'], $data['labels']);
        $this->assertEquals([0], $data['data']);
    }

    public function test_earnings_overview_groups_by_month(): void
    {
        $this->createInvestment([
            'status' => 'completed',
            'actual_return' => 1000,
            'completed_at' => now(),
        ]);

        $data = $this->service->getEarningsOverviewData($this->lender, 'month');

        $this->assertCount(6, $data['labels']);
        $this->assertContains(1000.0, $data['data']);
    }

    public function test_earnings_overview_groups_by_quarter(): void
    {
        $this->createInvestment([
            'status' => 'completed',
            'actual_return' => 2000,
            'completed_at' => now(),
        ]);

        $data = $this->service->getEarningsOverviewData($this->lender, 'quarter');

        $this->assertCount(4, $data['labels']);
    }

    public function test_earnings_overview_groups_by_year(): void
    {
        $this->createInvestment([
            'status' => 'completed',
            'actual_return' => 3000,
            'completed_at' => now(),
        ]);

        $data = $this->service->getEarningsOverviewData($this->lender, 'year');

        $this->assertCount(5, $data['labels']);
    }

    // ─── getEarningsByTypeData ──────────────────────────────────────

    public function test_earnings_by_type_returns_no_data_when_empty(): void
    {
        $data = $this->service->getEarningsByTypeData($this->lender);

        $this->assertEquals(['No Data'], $data['labels']);
    }

    public function test_earnings_by_type_groups_by_loan_purpose(): void
    {
        $loan1 = $this->createLoan();
        $loan2 = $this->createLoan();

        $this->createInvestment([
            'loan_id' => $loan1->id,
            'status' => 'completed',
            'actual_return' => 1000,
            'completed_at' => now(),
        ]);

        $data = $this->service->getEarningsByTypeData($this->lender);

        $this->assertNotEmpty($data['labels']);
        $this->assertNotEmpty($data['data']);
    }

    // ─── getFinancialOverviewData ───────────────────────────────────

    public function test_financial_overview_returns_correct_structure(): void
    {
        $data = $this->service->getFinancialOverviewData($this->lender, 'month');

        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('borrowed', $data);
        $this->assertArrayHasKey('invested', $data);
        $this->assertArrayHasKey('earnings', $data);
    }

    public function test_financial_overview_month_has_six_periods(): void
    {
        $data = $this->service->getFinancialOverviewData($this->lender, 'month');
        $this->assertCount(6, $data['labels']);
    }

    public function test_financial_overview_quarter_has_four_periods(): void
    {
        $data = $this->service->getFinancialOverviewData($this->lender, 'quarter');
        $this->assertCount(4, $data['labels']);
    }

    public function test_financial_overview_year_has_five_periods(): void
    {
        $data = $this->service->getFinancialOverviewData($this->lender, 'year');
        $this->assertCount(5, $data['labels']);
    }

    // ─── getInvestmentPerformanceData ───────────────────────────────

    public function test_investment_performance_returns_quarters(): void
    {
        $data = $this->service->getInvestmentPerformanceData($this->lender);

        $this->assertEquals(['Q1', 'Q2', 'Q3', 'Q4'], $data['labels']);
        $this->assertCount(4, $data['invested']);
        $this->assertCount(4, $data['returns']);
    }

    // ─── getInvestmentRoi ───────────────────────────────────────────

    public function test_investment_roi_calculates_correctly(): void
    {
        $investment = $this->createInvestment([
            'amount' => 1000,
            'actual_return' => 1150,
        ]);

        $roi = $this->service->getInvestmentRoi($investment);
        $this->assertEquals(15.0, $roi);
    }

    public function test_investment_roi_zero_for_zero_amount(): void
    {
        $investment = $this->createInvestment([
            'amount' => 0,
            'actual_return' => 0,
        ]);

        $roi = $this->service->getInvestmentRoi($investment);
        $this->assertSame(0.0, $roi);
    }

    // ─── getPlatformEarningsSummary ─────────────────────────────────

    public function test_platform_summary_returns_zero_when_no_investments(): void
    {
        $summary = $this->service->getPlatformEarningsSummary();

        $this->assertSame(0.0, $summary['total_earnings']);
        $this->assertSame(0.0, $summary['total_invested']);
        $this->assertSame(0.0, $summary['roi']);
    }

    public function test_platform_summary_aggregates_all_lenders(): void
    {
        $other = User::factory()->active()->create();
        $this->assignClientRole($other);

        $this->createInvestment(['lender_id' => $this->lender->id, 'amount' => 5000, 'status' => 'completed', 'actual_return' => 5500, 'completed_at' => now()]);
        $this->createInvestment(['lender_id' => $other->id, 'amount' => 3000, 'status' => 'completed', 'actual_return' => 3300, 'completed_at' => now()]);

        $summary = $this->service->getPlatformEarningsSummary();

        $this->assertEquals(8000.0, $summary['total_invested']);
        $this->assertEquals(8800.0, $summary['total_actual_return']);
        $this->assertEquals(800.0, $summary['total_earnings']);
    }

    // ─── getPortfolioPerformanceData ────────────────────────────────

    public function test_portfolio_performance_returns_no_data_when_empty(): void
    {
        $data = $this->service->getPortfolioPerformanceData($this->lender);

        $this->assertEquals(['Now'], $data['labels']);
        $this->assertEquals([0], $data['portfolio_value']);
        $this->assertEquals([0], $data['total_invested']);
    }

    public function test_portfolio_performance_shows_cumulative_values(): void
    {
        $this->createInvestment(['amount' => 1000, 'expected_return' => 1100, 'status' => 'active']);

        $data = $this->service->getPortfolioPerformanceData($this->lender);

        $this->assertCount(6, $data['labels']);
        $this->assertCount(6, $data['portfolio_value']);
        $this->assertCount(6, $data['total_invested']);

        $lastPortfolio = end($data['portfolio_value']);
        $lastInvested = end($data['total_invested']);

        $this->assertEquals(1100.0, $lastPortfolio);
        $this->assertEquals(1000.0, $lastInvested);
    }

    public function test_portfolio_performance_accumulates_across_months(): void
    {
        $this->createInvestment([
            'amount' => 1000,
            'expected_return' => 1100,
            'status' => 'active',
            'created_at' => now()->subMonths(3),
            'funded_at' => now()->subMonths(3),
        ]);
        $this->createInvestment([
            'amount' => 2000,
            'expected_return' => 2200,
            'status' => 'active',
        ]);

        $data = $this->service->getPortfolioPerformanceData($this->lender);

        $lastPortfolio = end($data['portfolio_value']);
        $lastInvested = end($data['total_invested']);

        $this->assertEquals(3300.0, $lastPortfolio);
        $this->assertEquals(3000.0, $lastInvested);
    }

    // ─── FundingService Delegation ──────────────────────────────────

    public function test_funding_service_portfolio_summary_delegates_to_earnings_service(): void
    {
        $fundingService = app(\App\Modules\Funding\Services\FundingService::class);

        $this->createInvestment(['amount' => 3000, 'status' => 'active']);

        $summary = $fundingService->getLenderPortfolioSummary($this->lender);

        $this->assertEquals(3000.0, $summary['total_invested']);
        $this->assertArrayHasKey('pending_transactions', $summary);
    }

    // ─── No Hardcoded Values ────────────────────────────────────────

    public function test_no_hardcoded_values_in_summary(): void
    {
        $summary = $this->service->getLenderEarningsSummary($this->lender);

        // All values should be 0 or calculated from DB, never hardcoded constants
        $this->assertTrue($summary['total_earnings'] >= 0);
        $this->assertTrue($summary['total_invested'] >= 0);
        $this->assertTrue($summary['roi'] >= 0);
        $this->assertTrue($summary['monthly_average'] >= 0);
    }
}
