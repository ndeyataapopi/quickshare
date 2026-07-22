<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanFinancialSummaryService;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanFinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected LoanFinancialSummaryService $summaryService;
    protected LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summaryService = app(LoanFinancialSummaryService::class);
        $this->loanService = app(LoanService::class);
    }

    protected function createBorrower(float $trustScore = 30.0): User
    {
        return User::factory()->active()->create(['trust_score' => $trustScore]);
    }

    protected function createLender(): User
    {
        return User::factory()->active()->create();
    }

    protected function createMarketplaceLoan(float $amount = 1000.00, float $platformFee = 50.00, float $lenderReturn = 250.00): Loan
    {
        $borrower = $this->createBorrower();
        $totalRepayment = $amount + $platformFee + $lenderReturn;

        return Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => $amount,
            'approved_amount' => $amount,
            'interest_rate' => 30.00,
            'platform_fee' => $platformFee,
            'total_repayment' => $totalRepayment,
            'funded_amount' => 0.00,
            'loan_term_days' => 30,
            'risk_score' => 30.00,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    protected function confirmFunding(Loan $loan, User $lender, float $amount): FundingTransaction
    {
        $ft = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => $amount,
            'interest_rate' => 25.00,
            'expected_return' => $this->loanService->expectedReturnForFunding($loan, $amount),
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
            'confirmed_at' => now(),
        ]);

        Investment::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'funding_transaction_id' => $ft->id,
            'amount' => $amount,
            'interest_rate' => 25.00,
            'expected_return' => $ft->expected_return,
            'actual_return' => 0,
            'status' => 'active',
            'funded_at' => now(),
        ]);

        $loan->update([
            'funded_amount' => (float) $loan->funded_amount + $amount,
        ]);

        return $ft;
    }

    protected function createDisbursement(Loan $loan): DisbursementTransaction
    {
        $loan->update(['status' => 'funded']);

        return DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'outgoing',
            'gross_amount' => (float) $loan->funded_amount,
            'platform_fee' => (float) $loan->platform_fee,
            'net_amount' => $this->loanService->disbursementAmount($loan),
            'status' => 'disbursed',
            'transaction_reference' => DisbursementTransaction::generateReference(),
            'payment_method' => 'bank_transfer',
            'processed_at' => now(),
            'borrower_confirmed_at' => now(),
        ]);
    }

    protected function createRepaymentSchedule(Loan $loan): Repayment
    {
        $calc = $this->loanService->repaymentCalculation($loan);

        return Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'amount' => $calc['amount'],
            'principal' => $calc['principal'],
            'interest' => $calc['lender_return'],
            'platform_fee' => $calc['platform_fee'],
            'penalty' => 0,
            'status' => 'pending',
            'due_date' => $loan->repayment_date ?? now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);
    }

    protected function approveRepayment(Loan $loan, Repayment $repayment): void
    {
        $paymentAmount = (float) $repayment->amount + (float) $repayment->penalty;

        $repayment->update([
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
        ]);

        DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'gross_amount' => $paymentAmount,
            'platform_fee' => 0,
            'net_amount' => $paymentAmount,
            'status' => 'confirmed',
            'transaction_reference' => DisbursementTransaction::generateReference(),
            'processed_at' => now(),
        ]);

        $fundings = FundingTransaction::forLoan($loan->id)->where('status', 'confirmed')->get();
        $totalFunded = $fundings->sum('amount');

        foreach ($fundings as $funding) {
            $dist = $this->loanService->lenderRepaymentDistribution($loan, $funding, $totalFunded, $paymentAmount);

            $lr = LenderRepayment::create([
                'repayment_id' => $repayment->id,
                'lender_id' => $funding->lender_id,
                'funding_transaction_id' => $funding->id,
                'amount' => $dist['amount'],
                'principal_return' => $dist['principal_return'],
                'interest_earned' => $dist['interest_earned'],
                'penalty_share' => 0,
                'funding_percentage' => $dist['funding_percentage'],
                'status' => 'processed',
                'processed_at' => now(),
                'transaction_reference' => LenderRepayment::generateReference(),
            ]);

            $investment = Investment::where('funding_transaction_id', $funding->id)->first();
            if ($investment) {
                $investment->increment('actual_return', (float) $lr->amount);
            }
        }
    }

    // ─── Tests ────────────────────────────────────────────────────────

    public function test_completed_loan_with_single_lender_reconciles(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $repayment = $this->createRepaymentSchedule($loan);
        $this->approveRepayment($loan, $repayment);
        $loan->update(['status' => 'completed', 'completed_at' => now()]);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertTrue($summary['reconciliation']['reconciled']);
        $this->assertEquals(1000.00 + 1300.00, $summary['reconciliation']['money_in']);
        $this->assertEquals(1000.00 + 1250.00, $summary['reconciliation']['money_out']);
        $this->assertEquals(50.00, $summary['reconciliation']['platform_revenue']);
    }

    public function test_completed_loan_with_multiple_lenders_reconciles(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lenderA = $this->createLender();
        $lenderB = $this->createLender();
        $lenderC = $this->createLender();

        $this->confirmFunding($loan, $lenderA, 500.00);
        $this->confirmFunding($loan, $lenderB, 300.00);
        $this->confirmFunding($loan, $lenderC, 200.00);

        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $repayment = $this->createRepaymentSchedule($loan);
        $this->approveRepayment($loan, $repayment);
        $loan->update(['status' => 'completed', 'completed_at' => now()]);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertTrue($summary['reconciliation']['reconciled']);
        $this->assertEquals(3, $summary['funding_summary']['investor_count']);
        $this->assertEquals(1250.00, collect($summary['lender_distribution'])->sum('total_paid'));
    }

    public function test_active_loan_with_no_repayments_reconciles(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $this->createRepaymentSchedule($loan);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertTrue($summary['reconciliation']['reconciled']);
        $this->assertEquals(1000.00, $summary['reconciliation']['money_in']);
        $this->assertEquals(1000.00, $summary['reconciliation']['money_out']);
        $this->assertEquals(0.00, $summary['reconciliation']['platform_revenue']);
    }

    public function test_partially_funded_loan_reconciles(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 400.00);
        $loan->update(['status' => 'partially_funded']);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertTrue($summary['reconciliation']['reconciled']);
        $this->assertEquals(400.00, $summary['funding_summary']['total_received']);
        $this->assertEquals(600.00, $summary['funding_summary']['remaining']);
        $this->assertEquals(40.00, $summary['funding_summary']['progress_percent']);
    }

    public function test_marketplace_loan_with_no_funding_reconciles(): void
    {
        $loan = $this->createMarketplaceLoan();

        $summary = $this->summaryService->generate($loan);

        $this->assertTrue($summary['reconciliation']['reconciled']);
        $this->assertEquals(0.00, $summary['reconciliation']['money_in']);
        $this->assertEquals(0.00, $summary['reconciliation']['money_out']);
        $this->assertEquals(0.00, $summary['reconciliation']['platform_revenue']);
    }

    public function test_overpayment_protection_flagged(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $repayment = $this->createRepaymentSchedule($loan);

        $repayment->update([
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
            'amount' => 1400.00,
        ]);

        $summary = $this->summaryService->generate($loan->fresh());

        $overpaymentCheck = collect($summary['reconciliation']['checks'])
            ->firstWhere('key', 'overpayment_protection');

        $this->assertFalse($overpaymentCheck['passed']);
    }

    public function test_loan_info_contains_all_required_fields(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $summary = $this->summaryService->generate($loan);

        $info = $summary['loan_info'];
        $this->assertEquals($loan->reference, $info['reference']);
        $this->assertEquals(1000.00, $info['approved_amount']);
        $this->assertEquals(50.00, $info['platform_fee']);
        $this->assertEquals(250.00, $info['lender_return']);
        $this->assertEquals('marketplace', $info['status']);
    }

    public function test_funding_summary_shows_individual_contributions(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lenderA = $this->createLender();
        $lenderB = $this->createLender();

        $this->confirmFunding($loan, $lenderA, 600.00);
        $this->confirmFunding($loan, $lenderB, 400.00);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertCount(2, $summary['funding_summary']['contributions']);
        $this->assertEquals(600.00, $summary['funding_summary']['contributions'][0]['amount']);
        $this->assertEquals(400.00, $summary['funding_summary']['contributions'][1]['amount']);
        $this->assertEquals(60.00, $summary['funding_summary']['contributions'][0]['funding_percentage']);
        $this->assertEquals(40.00, $summary['funding_summary']['contributions'][1]['funding_percentage']);
    }

    public function test_lender_distribution_shows_principal_and_interest(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $repayment = $this->createRepaymentSchedule($loan);
        $this->approveRepayment($loan, $repayment);
        $loan->update(['status' => 'completed', 'completed_at' => now()]);

        $summary = $this->summaryService->generate($loan->fresh());

        $dist = $summary['lender_distribution'][0];
        $this->assertEquals(1000.00, $dist['principal_returned']);
        $this->assertEquals(250.00, $dist['interest_earned']);
        $this->assertEquals(1250.00, $dist['total_paid']);
        $this->assertEquals('active', $dist['investment_status']);
    }

    public function test_repayment_summary_shows_scheduled_and_actual(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $this->createRepaymentSchedule($loan);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertEquals(1300.00, $summary['repayment_summary']['scheduled_total']);
        $this->assertEquals(0.00, $summary['repayment_summary']['actual_repaid']);
        $this->assertEquals(1300.00, $summary['repayment_summary']['outstanding_balance']);
    }

    public function test_discrepancy_detected_when_funding_does_not_match_investments(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $ft = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => 1000.00,
            'interest_rate' => 25.00,
            'expected_return' => 1250.00,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
            'confirmed_at' => now(),
        ]);

        Investment::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'funding_transaction_id' => $ft->id,
            'amount' => 500.00,
            'interest_rate' => 25.00,
            'expected_return' => 625.00,
            'actual_return' => 0,
            'status' => 'active',
            'funded_at' => now(),
        ]);

        $loan->update(['funded_amount' => 1000.00]);

        $summary = $this->summaryService->generate($loan->fresh());

        $investmentCheck = collect($summary['reconciliation']['checks'])
            ->firstWhere('key', 'investment_totals');

        $this->assertFalse($investmentCheck['passed']);
    }

    public function test_all_reconciliation_checks_present(): void
    {
        $loan = $this->createMarketplaceLoan();
        $summary = $this->summaryService->generate($loan);

        $checkKeys = collect($summary['reconciliation']['checks'])->pluck('key')->toArray();

        $this->assertContains('funding_totals', $checkKeys);
        $this->assertContains('investment_totals', $checkKeys);
        $this->assertContains('repayment_totals', $checkKeys);
        $this->assertContains('disbursement_totals', $checkKeys);
        $this->assertContains('lender_allocations', $checkKeys);
        $this->assertContains('platform_revenue', $checkKeys);
        $this->assertContains('money_equation', $checkKeys);
        $this->assertContains('overpayment_protection', $checkKeys);
    }

    public function test_completed_loan_has_additional_checks(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $repayment = $this->createRepaymentSchedule($loan);
        $this->approveRepayment($loan, $repayment);
        $loan->update(['status' => 'completed', 'completed_at' => now()]);

        $summary = $this->summaryService->generate($loan->fresh());

        $checkKeys = collect($summary['reconciliation']['checks'])->pluck('key')->toArray();

        $this->assertContains('lender_full_return', $checkKeys);
        $this->assertContains('repayment_full', $checkKeys);
        $this->assertContains('outstanding_zero', $checkKeys);
    }

    public function test_incoming_funds_show_funding_payments(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertCount(1, $summary['incoming_funds']);
        $this->assertEquals('funding', $summary['incoming_funds'][0]['type']);
        $this->assertEquals(1000.00, $summary['incoming_funds'][0]['amount']);
        $this->assertEquals('confirmed', $summary['incoming_funds'][0]['status']);
    }

    public function test_borrower_disbursement_shows_correct_net_amount(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertCount(1, $summary['borrower_disbursement']);
        $this->assertEquals(1000.00, $summary['borrower_disbursement'][0]['gross_amount']);
        $this->assertEquals(50.00, $summary['borrower_disbursement'][0]['platform_fee']);
        $this->assertEquals(1000.00, $summary['borrower_disbursement'][0]['net_amount']);
        $this->assertEquals('disbursed', $summary['borrower_disbursement'][0]['status']);
    }

    public function test_platform_summary_calculates_net_revenue(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);
        $this->createDisbursement($loan);
        $loan->update(['status' => 'active', 'disbursed_at' => now()]);
        $repayment = $this->createRepaymentSchedule($loan);

        $repayment->update(['penalty' => 100.00]);
        $this->approveRepayment($loan, $repayment);

        $summary = $this->summaryService->generate($loan->fresh());

        $this->assertEquals(50.00, $summary['platform_summary']['platform_fee_earned']);
        $this->assertEquals(100.00, $summary['platform_summary']['penalties_collected']);
        $this->assertEquals(150.00, $summary['platform_summary']['net_platform_revenue']);
    }

    public function test_summary_uses_live_database_data(): void
    {
        $loan = $this->createMarketplaceLoan(1000.00, 50.00, 250.00);
        $lender = $this->createLender();

        $this->confirmFunding($loan, $lender, 1000.00);

        $summary1 = $this->summaryService->generate($loan->fresh());
        $this->assertEquals(1000.00, $summary1['funding_summary']['total_received']);

        $lender2 = $this->createLender();
        $loan->update(['status' => 'marketplace']);
        $this->confirmFunding($loan, $lender2, 0.01);
        $loan->update(['funded_amount' => 1000.01]);

        $summary2 = $this->summaryService->generate($loan->fresh());
        $this->assertEquals(1000.01, $summary2['funding_summary']['total_received']);
    }
}
