<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\Loans\DTOs\AffordabilityInput;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\AffordabilityService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AffordabilityTest extends TestCase
{
    use RefreshDatabase;

    protected AffordabilityService $service;
    protected User $borrower;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(AffordabilityService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->borrower->assignRole('borrower');

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->admin->assignRole('admin');
    }

    protected function healthyInput(): AffordabilityInput
    {
        return new AffordabilityInput(
            monthlyIncome: 25000,
            monthlyExpenses: 10000,
            existingDebt: 5000,
            monthlyDebtRepayments: 2000,
            payslipGross: 30000,
            payslipNet: 25000,
            bankAvgBalance: 15000,
            bankAvgIncome: 24000,
            bankAvgExpenses: 11000,
        );
    }

    protected function strainedInput(): AffordabilityInput
    {
        return new AffordabilityInput(
            monthlyIncome: 8000,
            monthlyExpenses: 6000,
            existingDebt: 20000,
            monthlyDebtRepayments: 5000,
            payslipGross: 9000,
            payslipNet: 8000,
        );
    }

    // ─── DTI Ratio Tests ─────────────────────────────────────────────

    public function test_dti_calculation_is_correct(): void
    {
        $input = new AffordabilityInput(
            monthlyIncome: 20000,
            monthlyDebtRepayments: 4000,
        );

        $dti = $this->service->calculateDTI($input);

        $this->assertEquals(20.00, $dti);
    }

    public function test_dti_is_100_when_no_income(): void
    {
        $input = new AffordabilityInput(monthlyIncome: 0, monthlyDebtRepayments: 1000);

        $this->assertEquals(100.00, $this->service->calculateDTI($input));
    }

    public function test_dti_classification(): void
    {
        $this->assertEquals('excellent', $this->service->classifyDTI(15.00));
        $this->assertEquals('good', $this->service->classifyDTI(25.00));
        $this->assertEquals('fair', $this->service->classifyDTI(45.00));
        $this->assertEquals('poor', $this->service->classifyDTI(60.00));
    }

    // ─── Disposable Income Tests ─────────────────────────────────────

    public function test_disposable_income_calculation(): void
    {
        $input = new AffordabilityInput(
            monthlyIncome: 20000,
            monthlyExpenses: 8000,
            monthlyDebtRepayments: 3000,
        );

        $disposable = $this->service->calculateDisposableIncome($input);

        $this->assertEquals(9000.00, $disposable);
    }

    public function test_disposable_income_uses_bank_data_when_available(): void
    {
        $input = new AffordabilityInput(
            monthlyIncome: 20000,
            monthlyExpenses: 8000,
            monthlyDebtRepayments: 3000,
            bankAvgIncome: 18000,   // lower than declared
            bankAvgExpenses: 10000, // higher than declared
        );

        $disposable = $this->service->calculateDisposableIncome($input);

        // Uses min(20000, 18000) - max(8000, 10000) - 3000 = 5000
        $this->assertEquals(5000.00, $disposable);
    }

    public function test_disposable_income_cannot_be_negative(): void
    {
        $input = new AffordabilityInput(
            monthlyIncome: 5000,
            monthlyExpenses: 4000,
            monthlyDebtRepayments: 3000,
        );

        $disposable = $this->service->calculateDisposableIncome($input);

        $this->assertEquals(0.00, $disposable);
    }

    // ─── Max Monthly Repayment Tests ─────────────────────────────────

    public function test_max_monthly_repayment_is_30_percent_of_disposable(): void
    {
        $maxRepayment = $this->service->calculateMaxMonthlyRepayment(10000.00);

        $this->assertEquals(3000.00, $maxRepayment);
    }

    // ─── Affordability Score Tests ───────────────────────────────────

    public function test_healthy_borrower_gets_high_affordability_score(): void
    {
        $repaymentHistory = $this->service->getRepaymentHistory($this->borrower);

        $score = $this->service->calculateAffordabilityScore(
            $this->healthyInput(),
            65.00,
            $repaymentHistory,
        );

        $this->assertGreaterThan(50, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_strained_borrower_gets_low_affordability_score(): void
    {
        $repaymentHistory = $this->service->getRepaymentHistory($this->borrower);

        $score = $this->service->calculateAffordabilityScore(
            $this->strainedInput(),
            20.00,
            $repaymentHistory,
        );

        $this->assertLessThan(50, $score);
    }

    // ─── Risk Classification Tests ───────────────────────────────────

    public function test_risk_classification_levels(): void
    {
        $this->assertEquals('very_low', $this->service->classifyRisk(90, 10, 85));
        $this->assertEquals('low', $this->service->classifyRisk(70, 25, 70));
        $this->assertEquals('moderate', $this->service->classifyRisk(55, 40, 55));
        $this->assertEquals('high', $this->service->classifyRisk(40, 55, 40));
        $this->assertEquals('very_high', $this->service->classifyRisk(15, 70, 15));
    }

    // ─── Repayment History Tests ─────────────────────────────────────

    public function test_new_borrower_gets_default_reliability(): void
    {
        $history = $this->service->getRepaymentHistory($this->borrower);

        $this->assertEquals(0, $history['total_loans']);
        $this->assertEquals(50.00, $history['reliability']);
    }

    public function test_borrower_with_completed_loans_gets_high_reliability(): void
    {
        foreach (range(1, 5) as $i) {
            Loan::create([
                'borrower_id' => $this->borrower->id,
                'reference' => Loan::generateReference(),
                'requested_amount' => 1000,
                'interest_rate' => 15,
                'platform_fee' => 30,
                'total_repayment' => 1067,
                'loan_term_days' => 30,
                'status' => 'completed',
                'submitted_at' => now(),
            ]);
        }

        $history = $this->service->getRepaymentHistory($this->borrower);

        $this->assertEquals(5, $history['completed_loans']);
        $this->assertEquals(100.00, $history['reliability']);
    }

    public function test_defaults_heavily_reduce_reliability(): void
    {
        Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 1000,
            'interest_rate' => 15,
            'platform_fee' => 30,
            'total_repayment' => 1067,
            'loan_term_days' => 30,
            'status' => 'completed',
            'submitted_at' => now(),
        ]);
        Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 1000,
            'interest_rate' => 15,
            'platform_fee' => 30,
            'total_repayment' => 1067,
            'loan_term_days' => 30,
            'status' => 'defaulted',
            'submitted_at' => now(),
        ]);

        $history = $this->service->getRepaymentHistory($this->borrower);

        $this->assertEquals(1, $history['defaulted_loans']);
        $this->assertLessThan(50, $history['reliability']);
    }

    // ─── Max Loan Calculation Tests ──────────────────────────────────

    public function test_max_loan_respects_trust_tier(): void
    {
        $maxLoan = $this->service->calculateMaxLoan($this->borrower);

        // Silver tier (score 65) = 15000 max
        $this->assertEquals(15000.00, $maxLoan);
    }

    public function test_max_loan_limited_by_income(): void
    {
        $disposable = 2000.00; // Very low disposable income

        $maxLoan = $this->service->calculateMaxLoan($this->borrower, $disposable);

        $this->assertLessThan(15000.00, $maxLoan);
        $this->assertGreaterThan(0, $maxLoan);
    }

    // ─── Full Assessment Tests ───────────────────────────────────────

    public function test_full_assessment_creates_snapshot(): void
    {
        $assessment = $this->service->assess($this->borrower, $this->healthyInput());

        $this->assertNotNull($assessment->id);
        $this->assertEquals($this->borrower->id, $assessment->user_id);
        $this->assertNotNull($assessment->affordability_score);
        $this->assertNotNull($assessment->debt_to_income_ratio);
        $this->assertNotNull($assessment->disposable_income);
        $this->assertNotNull($assessment->max_loan_amount);
        $this->assertNotNull($assessment->risk_classification);
        $this->assertNotNull($assessment->decision);
        $this->assertEquals('silver', $assessment->trust_tier);

        $this->assertDatabaseHas('affordability_assessments', [
            'user_id' => $this->borrower->id,
        ]);
    }

    public function test_healthy_borrower_gets_approved(): void
    {
        // Boost trust score for clear approval
        $this->borrower->update(['trust_score' => 80.00]);

        $assessment = $this->service->assess($this->borrower->fresh(), $this->healthyInput());

        $this->assertEquals('approve', $assessment->decision);
    }

    public function test_strained_borrower_gets_rejected(): void
    {
        $this->borrower->update(['trust_score' => 20.00]);

        $assessment = $this->service->assess($this->borrower->fresh(), $this->strainedInput());

        $this->assertEquals('reject', $assessment->decision);
    }

    public function test_borderline_borrower_gets_manual_review(): void
    {
        $input = new AffordabilityInput(
            monthlyIncome: 15000,
            monthlyExpenses: 7000,
            monthlyDebtRepayments: 3000,
        );

        $assessment = $this->service->assess($this->borrower, $input);

        $this->assertContains($assessment->decision, ['manual_review', 'approve']);
    }

    public function test_multiple_defaults_trigger_rejection(): void
    {
        foreach (range(1, 2) as $i) {
            Loan::create([
                'borrower_id' => $this->borrower->id,
                'reference' => Loan::generateReference(),
                'requested_amount' => 1000,
                'interest_rate' => 15,
                'platform_fee' => 30,
                'total_repayment' => 1067,
                'loan_term_days' => 30,
                'status' => 'defaulted',
                'submitted_at' => now(),
            ]);
        }

        $assessment = $this->service->assess($this->borrower, $this->healthyInput());

        $this->assertEquals('reject', $assessment->decision);
        $this->assertStringContainsString('defaults', $assessment->decision_reasons);
    }

    // ─── approveOrRejectLoan Tests ───────────────────────────────────

    public function test_approve_or_reject_loan_creates_linked_assessment(): void
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

        $assessment = $this->service->approveOrRejectLoan($loan, $this->healthyInput());

        $this->assertEquals($loan->id, $assessment->loan_id);
        $this->assertEquals($this->borrower->id, $assessment->user_id);
    }

    // ─── Assessment History Tests ────────────────────────────────────

    public function test_assessment_history_is_stored(): void
    {
        $this->service->assess($this->borrower, $this->healthyInput());
        $this->service->assess($this->borrower, $this->strainedInput());

        $history = $this->service->getAssessmentHistory($this->borrower);

        $this->assertCount(2, $history);
    }

    public function test_latest_assessment_returns_most_recent(): void
    {
        $this->service->assess($this->borrower, $this->healthyInput());
        sleep(1);
        $second = $this->service->assess($this->borrower, $this->strainedInput());

        $latest = $this->service->getLatestAssessment($this->borrower);

        $this->assertEquals($second->id, $latest->id);
    }

    // ─── DTO Tests ───────────────────────────────────────────────────

    public function test_affordability_input_from_array(): void
    {
        $dto = AffordabilityInput::fromArray([
            'monthly_income' => 20000,
            'monthly_expenses' => 8000,
            'existing_debt' => 5000,
            'monthly_debt_repayments' => 2000,
            'payslip_gross' => 25000,
        ]);

        $this->assertEquals(20000, $dto->monthlyIncome);
        $this->assertEquals(8000, $dto->monthlyExpenses);
        $this->assertEquals(25000, $dto->payslipGross);
        $this->assertNull($dto->bankAvgBalance);
    }

    public function test_affordability_input_to_array(): void
    {
        $dto = new AffordabilityInput(monthlyIncome: 15000, monthlyExpenses: 5000);
        $arr = $dto->toArray();

        $this->assertEquals(15000, $arr['monthly_income']);
        $this->assertEquals(5000, $arr['monthly_expenses']);
        $this->assertArrayHasKey('bank_avg_balance', $arr);
    }

    // ─── API Endpoint Tests ──────────────────────────────────────────

    public function test_borrower_can_run_affordability_assessment(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/affordability/assess', [
            'monthly_income' => 25000,
            'monthly_expenses' => 10000,
            'monthly_debt_repayments' => 2000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'assessment' => [
                        'id', 'user_id', 'affordability_score',
                        'debt_to_income_ratio', 'disposable_income',
                        'max_loan_amount', 'risk_classification', 'decision',
                    ],
                ],
            ]);
    }

    public function test_borrower_can_get_max_loan(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/affordability/max-loan', [
            'monthly_income' => 25000,
            'monthly_expenses' => 10000,
            'monthly_debt_repayments' => 2000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'max_loan_amount', 'disposable_income',
                    'max_monthly_repayment', 'debt_to_income_ratio',
                    'dti_classification',
                ],
            ]);
    }

    public function test_borrower_can_view_assessment_history(): void
    {
        $this->service->assess($this->borrower, $this->healthyInput());

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson('/api/loans/affordability/history');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.assessments');
    }

    public function test_admin_can_run_assessment_for_loan(): void
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

        $response = $this->postJson("/api/loans/admin/{$loan->id}/affordability", [
            'monthly_income' => 25000,
            'monthly_expenses' => 10000,
            'monthly_debt_repayments' => 2000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assessment.loan_id', $loan->id);
    }

    public function test_assess_requires_monthly_income(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/affordability/assess', [
            'monthly_expenses' => 5000,
        ])->assertStatus(422);
    }

    // ─── RBAC Tests ──────────────────────────────────────────────────

    public function test_lender_cannot_access_affordability(): void
    {
        $lender = User::factory()->active()->create();
        $lender->assignRole('lender');

        Sanctum::actingAs($lender);

        $this->postJson('/api/loans/affordability/assess', [
            'monthly_income' => 20000,
        ])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_affordability(): void
    {
        $this->postJson('/api/loans/affordability/assess', [
            'monthly_income' => 20000,
        ])->assertStatus(401);
    }

    // ─── Model Tests ─────────────────────────────────────────────────

    public function test_assessment_model_helpers(): void
    {
        $assessment = $this->service->assess($this->borrower, $this->healthyInput());

        $this->assertIsBool($assessment->isApproved());
        $this->assertIsBool($assessment->isRejected());
        $this->assertIsBool($assessment->requiresManualReview());
    }

    public function test_user_has_affordability_assessments_relationship(): void
    {
        $this->service->assess($this->borrower, $this->healthyInput());

        $this->assertCount(1, $this->borrower->fresh()->affordabilityAssessments);
    }
}
