<?php

namespace Tests\Feature\Repayments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepaymentApprovalTest extends TestCase
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
        $this->admin = $this->admin->fresh();
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
            'expected_return' => $amount * 1.025,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);
    }

    protected function createInvestment(Loan $loan, FundingTransaction $funding): Investment
    {
        return Investment::create([
            'loan_id' => $loan->id,
            'lender_id' => $funding->lender_id,
            'funding_transaction_id' => $funding->id,
            'amount' => (float) $funding->amount,
            'interest_rate' => 15.00,
            'expected_return' => (float) $funding->expected_return,
            'actual_return' => 0,
            'status' => 'active',
            'funded_at' => now(),
        ]);
    }

    protected function createPendingApprovalRepayment(Loan $loan): Repayment
    {
        return Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'principal' => 10000,
            'interest' => 246,
            'platform_fee' => 300,
            'penalty' => 0,
            'status' => 'pending_approval',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);
    }

    protected function createIncomingDisbursement(Loan $loan, float $amount): DisbursementTransaction
    {
        return DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'gross_amount' => $amount,
            'platform_fee' => 0,
            'net_amount' => $amount,
            'status' => 'awaiting_approval',
            'transaction_reference' => DisbursementTransaction::generateReference(),
        ]);
    }

    // ─── Approve Tests ───────────────────────────────────────────────

    public function test_approve_sets_repayment_to_paid(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $result = $this->service->approveRepayment($repayment, $this->admin);

        $this->assertEquals('paid', $result->status);
        $this->assertNotNull($result->paid_date);
        $this->assertDatabaseHas('repayments', [
            'id' => $repayment->id,
            'status' => 'paid',
        ]);
    }

    public function test_approve_confirms_incoming_disbursement(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->approveRepayment($repayment, $this->admin);

        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'status' => 'confirmed',
        ]);
    }

    public function test_approve_creates_lender_repayments(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->approveRepayment($repayment, $this->admin);

        $lenderRepayments = LenderRepayment::where('repayment_id', $repayment->id)->get();
        $this->assertCount(1, $lenderRepayments);
        $this->assertEquals($this->lender->id, $lenderRepayments->first()->lender_id);
        $this->assertEquals(100.00, (float) $lenderRepayments->first()->funding_percentage);
    }

    public function test_approve_updates_investment_actual_return(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $investment = $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->approveRepayment($repayment, $this->admin);

        $investment->refresh();
        $this->assertGreaterThan(0, (float) $investment->actual_return);
    }

    public function test_approve_marks_loan_completed_when_fully_repaid(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->approveRepayment($repayment, $this->admin);

        $loan->refresh();
        $this->assertEquals('completed', $loan->status);
        $this->assertNotNull($loan->completed_at);
    }

    public function test_approve_does_not_complete_loan_if_other_repayments_exist(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);

        $repayment1 = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        // Create a second pending repayment
        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 5000,
            'status' => 'pending',
            'due_date' => now()->addDays(60),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->service->approveRepayment($repayment1, $this->admin);

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
    }

    public function test_approve_rejects_non_pending_approval_status(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);

        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Only repayments pending approval can be approved');

        $this->service->approveRepayment($repayment, $this->admin);
    }

    public function test_approve_with_multiple_lenders_distributes_proportionally(): void
    {
        $loan = $this->createActiveLoan();

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
        $this->createInvestment($loan, $funding1);

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
        Investment::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender2->id,
            'funding_transaction_id' => $funding2->id,
            'amount' => 4000,
            'interest_rate' => 15.00,
            'expected_return' => 4100,
            'actual_return' => 0,
            'status' => 'active',
            'funded_at' => now(),
        ]);

        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->approveRepayment($repayment, $this->admin);

        $lr1 = LenderRepayment::where('lender_id', $this->lender->id)->first();
        $lr2 = LenderRepayment::where('lender_id', $lender2->id)->first();

        $this->assertNotNull($lr1);
        $this->assertNotNull($lr2);
        $this->assertEquals(60.00, (float) $lr1->funding_percentage);
        $this->assertEquals(40.00, (float) $lr2->funding_percentage);

        $inv1 = Investment::where('funding_transaction_id', $funding1->id)->first();
        $inv2 = Investment::where('funding_transaction_id', $funding2->id)->first();
        $this->assertGreaterThan(0, (float) $inv1->actual_return);
        $this->assertGreaterThan(0, (float) $inv2->actual_return);
    }

    // ─── Reject Tests ────────────────────────────────────────────────

    public function test_reject_sets_repayment_to_rejected(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $result = $this->service->rejectRepayment($repayment, $this->admin, 'Invalid proof of payment');

        $this->assertEquals('rejected', $result->status);
        $this->assertDatabaseHas('repayments', [
            'id' => $repayment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_reject_marks_disbursement_rejected(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->rejectRepayment($repayment, $this->admin, 'Invalid proof');

        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'status' => 'rejected',
        ]);
    }

    public function test_reject_does_not_create_lender_repayments(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->rejectRepayment($repayment, $this->admin);

        $this->assertEquals(0, LenderRepayment::where('repayment_id', $repayment->id)->count());
    }

    public function test_reject_does_not_update_investment_earnings(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $investment = $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $originalReturn = (float) $investment->actual_return;

        $this->service->rejectRepayment($repayment, $this->admin);

        $investment->refresh();
        $this->assertEquals($originalReturn, (float) $investment->actual_return);
    }

    public function test_reject_does_not_change_loan_status(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $this->service->rejectRepayment($repayment, $this->admin);

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
    }

    public function test_reject_rejects_non_pending_approval_status(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);

        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
            'paid_date' => now(),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Only repayments pending approval can be rejected');

        $this->service->rejectRepayment($repayment, $this->admin);
    }

    public function test_reject_stores_reason_in_metadata(): void
    {
        $loan = $this->createActiveLoan();
        $funding = $this->createFunding($loan, 10000);
        $this->createInvestment($loan, $funding);
        $repayment = $this->createPendingApprovalRepayment($loan);
        $this->createIncomingDisbursement($loan, 10546);

        $result = $this->service->rejectRepayment($repayment, $this->admin, 'Proof of payment is unclear');

        $this->assertEquals('Proof of payment is unclear', $result->metadata['rejection_reason']);
        $this->assertEquals($this->admin->id, $result->metadata['rejected_by']);
    }

    // ─── Model Helper Tests ──────────────────────────────────────────

    public function test_repayment_is_rejected_helper(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'rejected',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertTrue($repayment->isRejected());
        $this->assertFalse($repayment->isPendingApproval());
        $this->assertFalse($repayment->isPaid());
    }

    public function test_repayment_rejected_scope(): void
    {
        $loan = $this->createActiveLoan();

        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'rejected',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 5000,
            'status' => 'pending',
            'due_date' => now()->addDays(60),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertEquals(1, Repayment::rejected()->count());
    }

    // ─── Loan Progress Fix Test ──────────────────────────────────────

    public function test_loan_progress_calculates_from_paid_repayments(): void
    {
        $loan = $this->createActiveLoan();

        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
            'paid_date' => now(),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $progress = $loan->progress();
        $this->assertEquals(100.00, $progress);
    }
}
