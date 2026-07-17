<?php

namespace Tests\Feature\Api\V1;

use App\Http\Resources\FundingTransactionResource;
use App\Http\Resources\KycSubmissionResource;
use App\Http\Resources\LoanResource;
use App\Http\Resources\MarketplaceLoanResource;
use App\Http\Resources\RepaymentResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    // ─── UserResource ─────────────────────────────────────────────────

    public function test_user_resource_has_correct_structure(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $resource = (new UserResource($user))->toArray(new Request());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('email', $resource);
        $this->assertArrayHasKey('full_name', $resource);
        $this->assertArrayHasKey('trust_score', $resource);
        $this->assertArrayHasKey('verification', $resource);
        $this->assertArrayHasKey('roles', $resource);
        $this->assertNotNull($resource['created_at']);
    }

    public function test_user_resource_includes_trust_score_tier(): void
    {
        $user = User::factory()->active()->create(['trust_score' => 75]);
        $this->assignClientRole($user);

        $resource = (new UserResource($user))->toArray(new Request());

        $this->assertArrayHasKey('score', $resource['trust_score']);
        $this->assertArrayHasKey('tier', $resource['trust_score']);
        $this->assertArrayHasKey('risk_level', $resource['trust_score']);
    }

    public function test_user_resource_hides_national_id(): void
    {
        $user = User::factory()->active()->create(['national_id' => 'ZA1234567890']);
        $this->assignClientRole($user);

        $resource = (new UserResource($user))->toArray(new Request());

        $this->assertArrayNotHasKey('national_id', $resource);
        $this->assertArrayNotHasKey('password', $resource);
    }

    public function test_user_resource_includes_verification_flags(): void
    {
        $user = User::factory()->active()->create([
            'email_verified_at' => now(),
            'phone_verified_at' => null,
        ]);
        $this->assignClientRole($user);

        $resource = (new UserResource($user))->toArray(new Request());

        $this->assertTrue($resource['verification']['email_verified']);
        $this->assertFalse($resource['verification']['phone_verified']);
    }

    // ─── LoanResource ─────────────────────────────────────────────────

    public function test_loan_resource_has_correct_structure(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);

        $resource = (new LoanResource($loan))->toArray(new Request());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('reference', $resource);
        $this->assertArrayHasKey('status', $resource);
        $this->assertArrayHasKey('amounts', $resource);
        $this->assertArrayHasKey('terms', $resource);
        $this->assertArrayHasKey('funding_progress', $resource);
        $this->assertArrayHasKey('timestamps', $resource);
    }

    public function test_loan_resource_calculates_funding_percentage(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create([
            'borrower_id' => $borrower->id,
            'approved_amount' => 10000,
            'funded_amount' => 5000,
        ]);

        $resource = (new LoanResource($loan))->toArray(new Request());

        $this->assertEquals(50.0, $resource['funding_progress']['percentage']);
        $this->assertFalse($resource['funding_progress']['fully_funded']);
    }

    public function test_loan_resource_marks_fully_funded(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create([
            'borrower_id' => $borrower->id,
            'approved_amount' => 5000,
            'funded_amount' => 5000,
        ]);

        $resource = (new LoanResource($loan))->toArray(new Request());

        $this->assertTrue($resource['funding_progress']['fully_funded']);
        $this->assertEquals(100.0, $resource['funding_progress']['percentage']);
    }

    public function test_loan_resource_omits_rejection_reason_when_null(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create([
            'borrower_id' => $borrower->id,
            'rejection_reason' => null,
        ]);

        $resource = (new LoanResource($loan))->resolve(new Request());

        $this->assertArrayNotHasKey('rejection_reason', $resource);
    }

    // ─── FundingTransactionResource ───────────────────────────────────

    public function test_funding_transaction_resource_structure(): void
    {
        $lender = User::factory()->active()->create();
        $this->assignClientRole($lender);

        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);

        $funding = FundingTransaction::factory()->create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => 2000,
            'expected_return' => 2300,
            'interest_rate' => 15,
        ]);

        $resource = (new FundingTransactionResource($funding))->toArray(new Request());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('transaction_reference', $resource);
        $this->assertArrayHasKey('status', $resource);
        $this->assertArrayHasKey('amounts', $resource);
        $this->assertEquals(2000.0, $resource['amounts']['funded']);
        $this->assertEquals(2300.0, $resource['amounts']['expected_return']);
    }

    // ─── RepaymentResource ────────────────────────────────────────────

    public function test_repayment_resource_structure(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);

        $repayment = Repayment::factory()->create([
            'loan_id' => $loan->id,
            'borrower_id' => $borrower->id,
            'amount' => 1500,
            'principal' => 1000,
            'interest' => 450,
            'penalty' => 0,
            'platform_fee' => 50,
        ]);

        $resource = (new RepaymentResource($repayment))->toArray(new Request());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('amounts', $resource);
        $this->assertArrayHasKey('dates', $resource);
        $this->assertEquals(1500.0, $resource['amounts']['total']);
        $this->assertEquals(0.0, $resource['amounts']['penalty']);
    }

    // ─── KycSubmissionResource ────────────────────────────────────────

    public function test_kyc_submission_resource_structure(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $kyc = KycSubmission::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $resource = (new KycSubmissionResource($kyc))->toArray(new Request());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('status', $resource);
        $this->assertArrayHasKey('missing_documents', $resource);
        $this->assertArrayHasKey('has_all_documents', $resource);
        $this->assertArrayHasKey('timestamps', $resource);
    }

    public function test_kyc_submission_resource_hides_admin_notes_from_borrower(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $kyc = KycSubmission::factory()->create([
            'user_id' => $user->id,
            'admin_notes' => 'Internal review note',
        ]);

        $request = Request::create('/api/v1/kyc/status', 'GET');
        $request->setUserResolver(fn () => $user);

        $resource = (new KycSubmissionResource($kyc))->resolve($request);

        $this->assertArrayNotHasKey('admin_notes', $resource);
    }

    // ─── MarketplaceLoanResource ──────────────────────────────────────

    public function test_marketplace_loan_resource_hides_borrower_identity(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);
        $loan->load('borrower');

        $resource = (new MarketplaceLoanResource($loan))->toArray(new Request());

        // Should show trust tier but NOT full user details
        $this->assertArrayHasKey('borrower', $resource);
        $this->assertArrayHasKey('trust_tier', $resource['borrower']);
        $this->assertArrayNotHasKey('email', $resource['borrower']);
        $this->assertArrayNotHasKey('phone', $resource['borrower']);
    }

    public function test_marketplace_loan_resource_calculates_remaining(): void
    {
        $borrower = User::factory()->active()->create();
        $this->assignClientRole($borrower);

        $loan = Loan::factory()->create([
            'borrower_id' => $borrower->id,
            'approved_amount' => 10000,
            'funded_amount' => 3000,
        ]);

        $resource = (new MarketplaceLoanResource($loan))->toArray(new Request());

        $this->assertEquals(7000.0, $resource['amounts']['remaining']);
    }
}
