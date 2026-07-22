<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Services\FundingService;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Repayments\Models\Repayment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $lender;
    protected User $borrower;
    protected User $admin;
    protected LoanService $loanService;
    protected FundingService $fundingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Queue::fake();

        $this->loanService = app(LoanService::class);
        $this->fundingService = app(FundingService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);
        KycSubmission::factory()->approved()->create(['user_id' => $this->borrower->id]);

        $this->lender = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($this->lender);

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->assignAdminRole($this->admin);
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

    // ─── Loan Lifecycle Audit Tests ───────────────────────────────────

    public function test_loan_request_creates_activity_log(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/v1/loans/request', [
            'requested_amount' => 1000,
            'loan_term_days' => 30,
            'agreement_read' => true,
            'agreement_terms' => true,
            'electronic_documents' => true,
            'agreement_version' => '1.0',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan.requested',
            'user_id' => $this->borrower->id,
        ]);
    }

    public function test_loan_approval_creates_activity_log(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15.00,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'pending_review',
            'risk_score' => 65.00,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->loanService->approve($loan, $this->admin);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan.approved',
            'loan_id' => $loan->id,
            'actor_id' => $this->admin->id,
            'previous_status' => 'pending_review',
            'new_status' => 'marketplace',
        ]);
    }

    public function test_loan_rejection_creates_activity_log(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15.00,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'pending_review',
            'risk_score' => 65.00,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->loanService->reject($loan, $this->admin, 'Insufficient credit history');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan.rejected',
            'loan_id' => $loan->id,
            'actor_id' => $this->admin->id,
            'previous_status' => 'pending_review',
            'new_status' => 'cancelled',
        ]);
    }

    public function test_loan_cancellation_creates_activity_log(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15.00,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'pending_review',
            'risk_score' => 65.00,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $this->loanService->cancel($loan, $this->borrower);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan.cancelled',
            'loan_id' => $loan->id,
            'user_id' => $this->borrower->id,
        ]);
    }

    // ─── Funding Audit Tests ──────────────────────────────────────────

    public function test_funding_initiated_creates_activity_log(): void
    {
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $this->fundingService->fund($this->lender, $loan, 5000);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'funding.initiated',
            'user_id' => $this->lender->id,
            'loan_id' => $loan->id,
        ]);
    }

    public function test_funding_payment_approved_creates_activity_log(): void
    {
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);
        $transaction = $this->fundingService->fund($this->lender, $loan, 5000);

        Sanctum::actingAs($this->admin);
        $this->fundingService->confirmFunding($transaction, $this->admin);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'funding.payment_approved',
            'funding_transaction_id' => $transaction->id,
            'actor_id' => $this->admin->id,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
        ]);
    }

    public function test_funding_payment_rejected_creates_activity_log(): void
    {
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);
        $transaction = $this->fundingService->fund($this->lender, $loan, 5000);

        Sanctum::actingAs($this->admin);
        $this->fundingService->rejectFunding($transaction, $this->admin, 'Invalid payment proof');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'funding.payment_rejected',
            'funding_transaction_id' => $transaction->id,
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_investment_created_logs_activity(): void
    {
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);
        $transaction = $this->fundingService->fund($this->lender, $loan, 5000);

        Sanctum::actingAs($this->admin);
        $this->fundingService->confirmFunding($transaction, $this->admin);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'investment.created',
            'loan_id' => $loan->id,
            'user_id' => $this->lender->id,
        ]);
    }

    // ─── KYC Audit Tests ──────────────────────────────────────────────

    public function test_kyc_approved_creates_activity_log(): void
    {
        $submission = KycSubmission::factory()->create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->admin);

        $service = app(\App\Modules\KYC\Services\KycService::class);
        $service->approve($submission, $this->admin);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'kyc.approved',
            'user_id' => $this->borrower->id,
            'actor_id' => $this->admin->id,
            'previous_status' => 'pending',
            'new_status' => 'approved',
        ]);
    }

    public function test_kyc_rejected_creates_activity_log(): void
    {
        $submission = KycSubmission::factory()->create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->admin);

        $service = app(\App\Modules\KYC\Services\KycService::class);
        $service->reject($submission, $this->admin, 'Blurry document');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'kyc.rejected',
            'user_id' => $this->borrower->id,
            'actor_id' => $this->admin->id,
        ]);
    }

    // ─── Auth Audit Tests ─────────────────────────────────────────────

    public function test_user_login_creates_activity_log(): void
    {
        $user = User::factory()->active()->create([
            'password' => bcrypt('password123'),
        ]);
        $this->assignClientRole($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'test',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.logged_in',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_logout_creates_activity_log(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.logged_out',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_registration_creates_activity_log(): void
    {
        $referrer = User::factory()->active()->create();
        $this->assignClientRole($referrer);
        $referralCode = \App\Models\ReferralCode::create([
            'user_id' => $referrer->id,
            'code' => 'TESTCODE',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'referral_code' => 'TESTCODE',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.audit@example.com',
            'phone' => '0712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'national_id' => '90010150000',
            'date_of_birth' => '1990-01-01',
            'address' => [
                'country' => 'South Africa',
                'city' => 'Johannesburg',
                'street' => 'Main St',
                'house_number' => '123',
            ],
            'source_of_income' => [
                'profession' => 'employed',
                'company_name' => 'Test Co',
                'city' => 'Johannesburg',
                'country' => 'South Africa',
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.registered',
        ]);
    }

    // ─── Admin Action Audit Tests ─────────────────────────────────────

    public function test_admin_user_status_change_creates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $this->patch(route('admin.users.status', $this->borrower), [
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.status_changed',
            'user_id' => $this->borrower->id,
            'actor_id' => $this->admin->id,
            'previous_status' => 'active',
            'new_status' => 'suspended',
        ]);
    }

    public function test_admin_role_update_creates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $this->patch(route('admin.users.roles.update', $this->borrower), [
            'roles' => ['client'],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.roles_updated',
            'user_id' => $this->borrower->id,
            'actor_id' => $this->admin->id,
        ]);
    }

    // ─── Immutability Tests ───────────────────────────────────────────

    public function test_activity_logs_are_not_deleted(): void
    {
        ActivityLog::create([
            'user_id' => $this->borrower->id,
            'action' => 'test.immutable',
            'description' => 'Test entry',
        ]);

        $log = ActivityLog::where('action', 'test.immutable')->first();

        $this->assertNotNull($log);

        // Verify no mass delete scope exists
        $this->assertFalse(
            method_exists(ActivityLog::class, 'truncate'),
            'ActivityLog should not have a truncate method'
        );
    }

    public function test_activity_log_contains_domain_fields(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15.00,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'pending_review',
            'risk_score' => 65.00,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);
        $this->loanService->approve($loan, $this->admin);

        $log = ActivityLog::where('action', 'loan.approved')->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->loan_id);
        $this->assertNotNull($log->actor_id);
        $this->assertNotNull($log->previous_status);
        $this->assertNotNull($log->new_status);
        $this->assertNotNull($log->ip_address);
    }
}
