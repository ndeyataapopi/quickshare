<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->active()->create([
            'email_verified_at' => now(),
            'trust_score' => 70,
        ]);
        $this->assignAdminRole($this->admin);
    }

    public function test_admin_can_view_operations_dashboard(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $response->assertOk();
        $response->assertViewHas([
            'start_of_day',
            'todays_loans',
            'pending_kyc',
            'loans_awaiting_approval',
            'funding_awaiting_approval',
            'disbursements_awaiting',
            'borrower_confirmations',
            'repayments_awaiting',
            'lender_payouts',
            'failed_jobs',
            'system_alerts',
        ]);
    }

    public function test_non_admin_cannot_access_operations_dashboard(): void
    {
        $client = User::factory()->active()->create();
        $this->assignClientRole($client);

        $response = $this->actingAs($client)
            ->get(route('admin.operations'));

        $response->assertForbidden();
    }

    public function test_todays_loans_counts_match_database(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 5750,
            'loan_term_days' => 30,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 3000,
            'approved_amount' => 3000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 3450,
            'loan_term_days' => 30,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 2000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 2300,
            'loan_term_days' => 30,
            'status' => 'cancelled',
            'submitted_at' => now(),
            'rejected_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $todaysLoans = $response->viewData('todays_loans');

        $this->assertEquals(3, $todaysLoans['submitted_today']);
        $this->assertEquals(1, $todaysLoans['pending_review']);
        $this->assertEquals(1, $todaysLoans['approved_today']);
        $this->assertEquals(1, $todaysLoans['rejected_today']);
    }

    public function test_pending_kyc_counts_match_database(): void
    {
        $user = User::factory()->active()->create();

        KycSubmission::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        KycSubmission::create([
            'user_id' => $user->id,
            'status' => 'resubmission_required',
            'submitted_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $pendingKyc = $response->viewData('pending_kyc');

        $this->assertEquals(1, $pendingKyc['pending_verification']);
        $this->assertEquals(1, $pendingKyc['resubmissions']);
        $this->assertNotNull($pendingKyc['oldest_pending']);
    }

    public function test_funding_awaiting_approval_counts_match_database(): void
    {
        $borrower = User::factory()->active()->create();
        $lender = User::factory()->active()->create();

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 11500,
            'loan_term_days' => 30,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => 5000,
            'interest_rate' => 15,
            'expected_return' => 5750,
            'status' => 'pending',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $funding = $response->viewData('funding_awaiting_approval');

        $this->assertEquals(1, $funding['pending_proofs']);
        $this->assertEquals(5000.00, $funding['total_amount']);
    }

    public function test_disbursements_awaiting_counts_match_database(): void
    {
        $borrower = User::factory()->active()->create();

        Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 11500,
            'funded_amount' => 10000,
            'loan_term_days' => 30,
            'status' => 'funded',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $disbursements = $response->viewData('disbursements_awaiting');

        $this->assertEquals(1, $disbursements['count']);
        $this->assertEquals(10000.00, $disbursements['total_amount']);
    }

    public function test_borrower_confirmations_counts_match_database(): void
    {
        $borrower = User::factory()->active()->create();

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 11500,
            'funded_amount' => 10000,
            'loan_term_days' => 30,
            'status' => 'awaiting_disbursement',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'outgoing',
            'gross_amount' => 10000,
            'platform_fee' => 300,
            'net_amount' => 9700,
            'status' => 'pending_borrower_confirmation',
            'transaction_reference' => DisbursementTransaction::generateReference(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $confirmations = $response->viewData('borrower_confirmations');

        $this->assertEquals(1, $confirmations['count']);
    }

    public function test_repayments_awaiting_approval_counts_match_database(): void
    {
        $borrower = User::factory()->active()->create();

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 5750,
            'funded_amount' => 5000,
            'loan_term_days' => 30,
            'status' => 'active',
            'submitted_at' => now(),
            'approved_at' => now(),
            'disbursed_at' => now(),
        ]);

        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $borrower->id,
            'amount' => 5750,
            'principal' => 5000,
            'interest' => 650,
            'platform_fee' => 100,
            'status' => 'pending_approval',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $repayments = $response->viewData('repayments_awaiting');

        $this->assertEquals(1, $repayments['count']);
        $this->assertEquals(5750.00, $repayments['total_amount']);
    }

    public function test_lender_payouts_counts_match_database(): void
    {
        $borrower = User::factory()->active()->create();
        $lender = User::factory()->active()->create();

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 5750,
            'funded_amount' => 5000,
            'loan_term_days' => 30,
            'status' => 'active',
            'submitted_at' => now(),
            'approved_at' => now(),
            'disbursed_at' => now(),
        ]);

        $funding = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => 5000,
            'interest_rate' => 15,
            'expected_return' => 5750,
            'status' => 'confirmed',
            'transaction_reference' => FundingTransaction::generateReference(),
            'confirmed_at' => now(),
        ]);

        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $borrower->id,
            'amount' => 5750,
            'principal' => 5000,
            'interest' => 650,
            'platform_fee' => 100,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
            'paid_date' => today(),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        LenderRepayment::create([
            'repayment_id' => $repayment->id,
            'lender_id' => $lender->id,
            'funding_transaction_id' => $funding->id,
            'amount' => 5750,
            'principal_return' => 5000,
            'interest_earned' => 650,
            'penalty_share' => 0,
            'funding_percentage' => 100,
            'status' => 'processed',
            'processed_at' => now(),
            'transaction_reference' => LenderRepayment::generateReference(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $payouts = $response->viewData('lender_payouts');

        $this->assertEquals(1, $payouts['lenders_waiting']);
        $this->assertEquals(5750.00, $payouts['total_amount']);
    }

    public function test_system_alerts_shows_overdue_loans(): void
    {
        $borrower = User::factory()->active()->create();

        Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'approved_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 5750,
            'funded_amount' => 5000,
            'loan_term_days' => 30,
            'status' => 'overdue',
            'submitted_at' => now(),
            'approved_at' => now(),
            'disbursed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $alerts = $response->viewData('system_alerts');

        $this->assertNotEmpty($alerts);
        $this->assertTrue(
            collect($alerts)->contains(fn ($a) => str_contains($a['message'], 'overdue'))
        );
    }

    public function test_start_of_day_summary_totals_match_database(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        KycSubmission::create([
            'user_id' => $borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 100,
            'total_repayment' => 5750,
            'loan_term_days' => 30,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $startOfDay = $response->viewData('start_of_day');

        $this->assertEquals(1, $startOfDay['items'][0]['count']); // Pending KYC
        $this->assertEquals(1, $startOfDay['items'][1]['count']); // Loans Awaiting Approval
        $this->assertEquals(2, $startOfDay['total']);
    }

    public function test_view_queue_buttons_link_to_correct_routes(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.operations'));

        $response->assertSee(route('admin.kyc.index'));
        $response->assertSee(route('admin.loans.index'));
        $response->assertSee(route('admin.funding-payments.index'));
        $response->assertSee(route('admin.disbursements.index'));
        $response->assertSee(route('admin.repayments.index'));
        $response->assertSee(route('admin.system-status.index'));
    }
}
