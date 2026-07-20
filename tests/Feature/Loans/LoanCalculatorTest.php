<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Services\LoanService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->loanService = app(LoanService::class);
    }

    protected function borrower(float $trustScore): User
    {
        $borrower = User::factory()->active()->create(['trust_score' => $trustScore]);
        $this->assignClientRole($borrower);

        return $borrower;
    }

    #[DataProvider('tierDataProvider')]
    public function test_calculator_uses_correct_tier_percentages(string $tier, float $score, float $amount): void
    {
        $borrower = $this->borrower($score);
        $termDays = 21;

        $calc = $this->loanService->calculate($borrower, $amount, $termDays);

        $tierConfig = config("loan.trust_tiers.{$tier}");
        $expectedPlatform = round($amount * ($tierConfig['platform_fee_percent'] / 100), 2);
        $expectedLender = round($amount * ($tierConfig['lender_return_percent'] / 100), 2);
        $expectedInterest = round($expectedPlatform + $expectedLender, 2);
        $expectedTotal = round($amount + $expectedInterest, 2);
        $expectedRate = $tierConfig['platform_fee_percent'] + $tierConfig['lender_return_percent'];

        $this->assertSame($tier, $calc->trustTier);
        $this->assertEquals($expectedRate, $calc->interestRate);
        $this->assertEquals($expectedPlatform, $calc->platformFee);
        $this->assertEquals($expectedLender, $calc->lenderReturnAmount);
        $this->assertEquals($expectedInterest, $calc->interestAmount);
        $this->assertEquals($expectedTotal, $calc->totalRepayment);
        $this->assertEquals($amount, $calc->principal);
    }

    public static function tierDataProvider(): array
    {
        return [
            'bronze' => ['bronze', 30.00, 500.00],
            'silver' => ['silver', 55.00, 1000.00],
            'gold' => ['gold', 75.00, 1500.00],
            'platinum' => ['platinum', 90.00, 1500.00],
        ];
    }

    public function test_bronze_example_from_business_rule(): void
    {
        $borrower = $this->borrower(30.00);

        $calc = $this->loanService->calculate($borrower, 500.00, 30);

        $this->assertEquals(25.00, $calc->platformFee);   // 5% of 500
        $this->assertEquals(125.00, $calc->lenderReturnAmount); // 25% of 500
        $this->assertEquals(150.00, $calc->interestAmount); // 25 + 125
        $this->assertEquals(650.00, $calc->totalRepayment); // 500 + 150
    }

    public function test_interest_is_independent_of_loan_term(): void
    {
        $borrower = $this->borrower(55.00);

        $calc30 = $this->loanService->calculate($borrower, 1000.00, 30);
        $calc90 = $this->loanService->calculate($borrower, 1000.00, 90);

        $this->assertEquals($calc30->interestAmount, $calc90->interestAmount);
        $this->assertEquals($calc30->totalRepayment, $calc90->totalRepayment);
        $this->assertNotEquals($calc30->repaymentDate, $calc90->repaymentDate);
    }

    #[DataProvider('boundaryScoreProvider')]
    public function test_trust_tier_boundaries(float $score, string $expectedTier): void
    {
        $borrower = $this->borrower($score);

        $calc = $this->loanService->calculate($borrower, 500.00, 30);

        $this->assertSame($expectedTier, $calc->trustTier);
    }

    public static function boundaryScoreProvider(): array
    {
        return [
            'bronze minimum' => [0.00, 'bronze'],
            'bronze maximum' => [49.99, 'bronze'],
            'silver minimum' => [50.00, 'silver'],
            'silver maximum' => [69.99, 'silver'],
            'gold minimum' => [70.00, 'gold'],
            'gold maximum' => [84.99, 'gold'],
            'platinum minimum' => [85.00, 'platinum'],
            'platinum maximum' => [100.00, 'platinum'],
        ];
    }

    public function test_calculation_api_returns_required_calculator_fields(): void
    {
        $borrower = $this->borrower(55.00);
        KycSubmission::factory()->approved()->create(['user_id' => $borrower->id]);
        Sanctum::actingAs($borrower);

        $response = $this->postJson('/api/loans/calculate', [
            'amount' => 1000.00,
            'term_days' => 21,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'principal', 'interest_rate', 'term_days', 'interest_amount',
                    'platform_fee', 'platform_fee_percent', 'lender_return_amount',
                    'lender_return_percent', 'total_interest_amount', 'total_interest_percent',
                    'total_repayment', 'repayment_date', 'risk_score', 'risk_level',
                    'trust_tier', 'max_allowed_amount',
                ],
            ]);
    }

    public function test_api_calculation_fails_when_amount_exceeds_tier_limit(): void
    {
        $borrower = $this->borrower(55.00);
        KycSubmission::factory()->approved()->create(['user_id' => $borrower->id]);
        Sanctum::actingAs($borrower);

        $this->postJson('/api/loans/calculate', [
            'amount' => config('loan.trust_tiers.silver.maximum_loan') + 1,
            'term_days' => 21,
        ])->assertUnprocessable();
    }

    public function test_loan_request_fails_when_amount_exceeds_tier_limit(): void
    {
        $borrower = $this->borrower(55.00);
        KycSubmission::factory()->approved()->create(['user_id' => $borrower->id]);
        Sanctum::actingAs($borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => config('loan.trust_tiers.silver.maximum_loan') + 1,
            'loan_term_days' => 21,
            'agreement_read' => true,
            'agreement_terms' => true,
            'electronic_documents' => true,
            'agreement_version' => config('loan.agreement.version'),
        ])->assertUnprocessable();
    }

    public function test_total_repayment_equals_principal_plus_total_interest(): void
    {
        $borrower = $this->borrower(90.00);

        $calc = $this->loanService->calculate($borrower, 1500.00, 14);

        $this->assertEquals(
            round($calc->principal + $calc->interestAmount, 2),
            $calc->totalRepayment,
        );
        $this->assertEquals(
            round($calc->platformFee + $calc->lenderReturnAmount, 2),
            $calc->interestAmount,
        );
    }
}
