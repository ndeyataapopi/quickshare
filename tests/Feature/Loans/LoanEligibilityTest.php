<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_verified_borrower_at_minimum_trust_score_can_request_a_loan(): void
    {
        $borrower = $this->createBorrower((float) config('loan.minimum_borrow_score'));
        KycSubmission::factory()->approved()->create(['user_id' => $borrower->id]);

        Sanctum::actingAs($borrower);

        $this->postJson('/api/loans/request', $this->validLoanRequest())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loan.borrower_id', $borrower->id);
    }

    public function test_borrower_without_kyc_verification_cannot_request_a_loan(): void
    {
        $borrower = $this->createBorrower(50.00);

        Sanctum::actingAs($borrower);

        $this->postJson('/api/loans/request', $this->validLoanRequest())
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'You must complete KYC verification before requesting a loan.',
            ]);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_borrower_with_unverified_kyc_cannot_request_a_loan(): void
    {
        $borrower = $this->createBorrower(50.00);
        KycSubmission::factory()->create(['user_id' => $borrower->id]);

        Sanctum::actingAs($borrower);

        $this->postJson('/api/loans/request', $this->validLoanRequest())
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'You must complete KYC verification before requesting a loan.',
            ]);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_verified_borrower_below_minimum_trust_score_cannot_request_a_loan(): void
    {
        $minimumScore = (float) config('loan.minimum_borrow_score');
        $borrower = $this->createBorrower($minimumScore - 0.01);
        KycSubmission::factory()->approved()->create(['user_id' => $borrower->id]);

        Sanctum::actingAs($borrower);

        $this->postJson('/api/loans/request', $this->validLoanRequest())
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => "Your trust score is too low to borrow. Minimum required: {$minimumScore}",
            ]);

        $this->assertDatabaseCount('loans', 0);
    }

    protected function createBorrower(float $trustScore): User
    {
        $borrower = User::factory()->active()->create(['trust_score' => $trustScore]);
        $this->assignClientRole($borrower);

        return $borrower;
    }

    protected function validLoanRequest(): array
    {
        return [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => (int) config('loan.trust_tiers.bronze.allowed_durations.0'),
            'agreement_read' => true,
            'agreement_terms' => true,
            'electronic_documents' => true,
            'agreement_version' => (string) config('loan.agreement.version'),
        ];
    }
}
