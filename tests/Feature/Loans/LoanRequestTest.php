<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\DTOs\LoanRequestData;
use App\Modules\Loans\Mail\LoanAgreementMail;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanAgreementService;
use App\Modules\Loans\Services\LoanService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $borrower;

    protected User $admin;

    protected LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake((string) config('loan.agreement.disk'));
        Mail::fake();
        $this->loanService = app(LoanService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 55.00]);
        $this->assignClientRole($this->borrower);
        KycSubmission::factory()->approved()->create(['user_id' => $this->borrower->id]);

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->assignAdminRole($this->admin);
    }

    // ─── Loan Calculation Tests ──────────────────────────────────────

    public function test_loan_calculation_returns_correct_values(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 10000.00, 90);

        $this->assertEquals(10000.00, $calc->principal);
        $this->assertEquals((float) config('loan.trust_tiers.silver.interest_percent'), $calc->interestRate);
        $this->assertEquals(90, $calc->termDays);
        $this->assertGreaterThan(0, $calc->interestAmount);
        $this->assertGreaterThan(0, $calc->platformFee);
        $this->assertGreaterThan($calc->principal, $calc->totalRepayment);
        $this->assertEquals('silver', $calc->trustTier);
    }

    public function test_interest_calculation_is_proportional_to_term(): void
    {
        $calc30 = $this->loanService->calculate($this->borrower, 10000.00, 30);
        $calc90 = $this->loanService->calculate($this->borrower, 10000.00, 90);

        $this->assertGreaterThan($calc30->interestAmount, $calc90->interestAmount);
        // 90-day interest should be approx 3x the 30-day interest
        $ratio = $calc90->interestAmount / $calc30->interestAmount;
        $this->assertEqualsWithDelta(3.0, $ratio, 0.01);
    }

    public function test_platform_fee_is_percentage_of_principal(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 10000.00, 30);
        $expectedFee = round(10000 * (config('loan.trust_tiers.silver.platform_fee_percent') / 100), 2);

        $this->assertEquals($expectedFee, $calc->platformFee);
    }

    public function test_loan_calculation_uses_configured_tier_rules(): void
    {
        config([
            'loan.trust_tiers.silver.name' => 'custom-silver',
            'loan.trust_tiers.silver.maximum_loan' => 4321.00,
            'loan.trust_tiers.silver.interest_percent' => 12.50,
            'loan.trust_tiers.silver.platform_fee_percent' => 2.25,
        ]);

        $calculation = $this->loanService->calculate($this->borrower, 1000.00, 30);

        $this->assertEquals('custom-silver', $calculation->trustTier);
        $this->assertEquals(4321.00, $calculation->maxAllowedAmount);
        $this->assertEquals(12.50, $calculation->interestRate);
        $this->assertEquals(22.50, $calculation->platformFee);
    }

    public function test_total_repayment_equals_principal_plus_interest_plus_fee(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 10000.00, 60);

        $expected = round($calc->principal + $calc->interestAmount + $calc->platformFee, 2);
        $this->assertEquals($expected, $calc->totalRepayment);
    }

    public function test_calculation_dto_to_array(): void
    {
        $calc = $this->loanService->calculate($this->borrower, 5000.00, 30);
        $arr = $calc->toArray();

        $this->assertArrayHasKey('principal', $arr);
        $this->assertArrayHasKey('interest_rate', $arr);
        $this->assertArrayHasKey('interest_amount', $arr);
        $this->assertArrayHasKey('platform_fee', $arr);
        $this->assertArrayHasKey('total_repayment', $arr);
        $this->assertArrayHasKey('risk_score', $arr);
        $this->assertArrayHasKey('risk_level', $arr);
        $this->assertArrayHasKey('trust_tier', $arr);
        $this->assertArrayHasKey('max_allowed_amount', $arr);
    }

    // ─── Loan Request API Tests ──────────────────────────────────────

    public function test_borrower_can_request_loan(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
            ...$this->validAgreementConsent(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loan.status', 'pending_review')
            ->assertJsonStructure([
                'data' => [
                    'loan' => [
                        'id', 'reference', 'requested_amount', 'interest_rate',
                        'platform_fee', 'total_repayment', 'loan_term_days',
                        'risk_score', 'status',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('loans', [
            'borrower_id' => $this->borrower->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_loan_request_generates_unique_reference(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
            ...$this->validAgreementConsent(),
        ]);

        $loan = Loan::first();
        $this->assertStringStartsWith('QS-', $loan->reference);
        $this->assertEquals(15, strlen($loan->reference)); // QS- + 12 hex chars
    }

    public function test_loan_request_generates_and_stores_complete_pdf_agreement(): void
    {
        Sanctum::actingAs($this->borrower);
        $amount = (float) config('loans.min_amount');
        $termDays = max(config('loan.trust_tiers.silver.allowed_durations'));

        $this->withHeader('User-Agent', 'QuickShare-Test')->postJson('/api/loans/request', [
            'requested_amount' => $amount,
            'loan_term_days' => $termDays,
            ...$this->validAgreementConsent(),
        ])->assertCreated();

        $loan = Loan::firstOrFail();
        $calculation = $this->loanService->calculate($this->borrower, $amount, $termDays);
        $agreementData = app(LoanAgreementService::class)->data($loan, $calculation, $loan->repayment_date);
        $html = view('pdf.loan-agreement', $agreementData)->render();

        Storage::disk((string) config('loan.agreement.disk'))->assertExists($loan->agreement_path);
        $this->assertStringStartsWith('%PDF', Storage::disk((string) config('loan.agreement.disk'))->get($loan->agreement_path));
        $this->assertEquals(config('loan.agreement.version'), $loan->agreement_version);
        $this->assertNotNull($loan->agreement_generated_at);
        $this->assertNotNull($loan->repayment_date);
        $this->assertNotNull($loan->configuration_snapshot);
        $this->assertSame('silver', $loan->configuration_snapshot['trust_tier']['name']);
        $this->assertEquals($calculation->totalRepayment, $loan->configuration_snapshot['calculation']['total_repayment']);
        $this->assertSame([
            'agreement_read' => true,
            'agreement_terms_accepted' => true,
            'electronic_documents_consented' => true,
        ], $loan->agreement_consent);
        $this->assertSame('127.0.0.1', $loan->agreement_ip_address);
        $this->assertSame('QuickShare-Test', $loan->agreement_user_agent);
        $this->assertNotNull($loan->agreement_consented_at);
        $this->assertStringContainsString($this->borrower->full_name, $html);
        $this->assertStringContainsString($loan->reference, $html);
        $this->assertStringContainsString('Trust Tier', $html);
        $this->assertStringContainsString('Trust Score', $html);
        $this->assertStringContainsString('Loan Amount', $html);
        $this->assertStringContainsString('Interest', $html);
        $this->assertStringContainsString('Platform Fee', $html);
        $this->assertStringContainsString('Lender Return', $html);
        $this->assertStringContainsString('Repayment', $html);
        $this->assertStringContainsString('Repayment Date', $html);
        $this->assertStringContainsString(config('loan.agreement.terms'), $html);
        $this->assertStringContainsString(config('loan.agreement.conditions'), $html);
        $this->assertStringContainsString('Agreement Version '.config('loan.agreement.version'), $html);
        $this->assertStringContainsString(number_format($calculation->totalRepayment, 2), $html);
        Mail::assertQueued(LoanAgreementMail::class, function (LoanAgreementMail $mail) use ($loan): bool {
            $mail->assertTo($this->borrower->email);
            $mail->assertSeeInHtml('Loan Summary');
            $mail->assertSeeInHtml($loan->reference);
            $mail->assertSeeInHtml(number_format((float) $loan->total_repayment, 2));
            $mail->assertSeeInHtml($loan->repayment_date->format('d F Y'));
            $mail->assertHasAttachment(
                Attachment::fromStorageDisk(
                    (string) config('loan.agreement.disk'),
                    $loan->agreement_path,
                )->as("loan-agreement-{$loan->reference}.pdf")
                    ->withMime('application/pdf'),
            );

            return $mail->loan->is($loan) && $mail->queue === 'notifications';
        });
    }

    public function test_loan_request_fails_below_minimum_amount(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount') - 1,
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_above_trust_tier_limit(): void
    {
        // Silver tier max is 15000
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loan.trust_tiers.silver.maximum_loan') + 1,
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_with_invalid_term(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => min(config('loan.trust_tiers.silver.allowed_durations')) - 1, // below 30 min
        ])->assertStatus(422);

        $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')) + 1, // above 365 max
        ])->assertStatus(422);
    }

    public function test_loan_request_rejects_duration_not_allowed_for_tier(): void
    {
        config(['loan.trust_tiers.silver.allowed_durations' => [14, 28]]);
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => 21,
        ])->assertStatus(422);
    }

    public function test_loan_request_fails_for_low_trust_score(): void
    {
        $lowTrustUser = User::factory()->active()->create(['trust_score' => 20.00]);
        $this->assignClientRole($lowTrustUser);
        KycSubmission::factory()->approved()->create(['user_id' => $lowTrustUser->id]);

        Sanctum::actingAs($lowTrustUser);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 1000.00,
            'loan_term_days' => 30,
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_for_inactive_user(): void
    {
        $inactive = User::factory()->create(['trust_score' => 60.00, 'status' => 'suspended']);
        $this->assignClientRole($inactive);

        Sanctum::actingAs($inactive);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_fails_when_max_active_loans_reached(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Loan::create([
                'borrower_id' => $this->borrower->id,
                'reference' => Loan::generateReference(),
                'requested_amount' => 1000,
                'interest_rate' => 15,
                'platform_fee' => 30,
                'total_repayment' => 1067,
                'loan_term_days' => 30,
                'status' => 'active',
                'submitted_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/request', [
            'requested_amount' => 1000.00,
            'loan_term_days' => 30,
        ]);

        $response->assertStatus(422);
    }

    public function test_loan_request_requires_current_agreement_acceptance(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
            'agreement_version' => 'outdated',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'agreement_read',
                'agreement_terms',
                'electronic_documents',
                'agreement_version',
            ]);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_invalid_loan_configuration_prevents_creation(): void
    {
        config(['loan.agreement.terms' => '']);
        $data = LoanRequestData::fromArray([
            'borrower_id' => $this->borrower->id,
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
            ...$this->validAgreementConsent(),
        ]);

        try {
            $this->loanService->requestLoan($data);
            $this->fail('Invalid loan configuration should prevent creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Loan configuration is invalid.', $exception->getMessage());
        }

        $this->assertDatabaseCount('loans', 0);
    }

    protected function validAgreementConsent(): array
    {
        return [
            'agreement_read' => true,
            'agreement_terms' => true,
            'electronic_documents' => true,
            'agreement_version' => (string) config('loan.agreement.version'),
        ];
    }

    // ─── Calculation API Tests ───────────────────────────────────────

    public function test_borrower_can_calculate_loan(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/loans/calculate', [
            'amount' => (float) config('loan.trust_tiers.silver.maximum_loan'),
            'term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'principal', 'interest_rate', 'term_days',
                    'interest_amount', 'platform_fee', 'total_repayment',
                    'risk_score', 'risk_level', 'trust_tier', 'max_allowed_amount',
                ],
            ]);
    }

    // ─── Borrower Loan List Tests ────────────────────────────────────

    public function test_borrower_can_view_own_loans(): void
    {
        Loan::create([
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

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson('/api/loans');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_borrower_cannot_view_other_users_loan(): void
    {
        $otherBorrower = User::factory()->active()->create(['trust_score' => 55.00]);
        $this->assignClientRole($otherBorrower);

        $loan = Loan::create([
            'borrower_id' => $otherBorrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $this->getJson("/api/loans/{$loan->id}")->assertStatus(403);
    }

    // ─── Cancel Tests ────────────────────────────────────────────────

    public function test_borrower_can_cancel_draft_or_pending_loan(): void
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

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson("/api/loans/{$loan->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.loan.status', 'cancelled');
    }

    public function test_borrower_cannot_cancel_active_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'active',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $this->postJson("/api/loans/{$loan->id}/cancel")->assertStatus(422);
    }

    // ─── Admin Review Tests ──────────────────────────────────────────

    public function test_admin_can_approve_loan(): void
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

        $response = $this->postJson("/api/loans/admin/{$loan->id}/approve", [
            'admin_notes' => 'Looks good.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.loan.status', 'marketplace');

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'marketplace',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_approve_with_different_amount(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'loan_term_days' => 60,
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/loans/admin/{$loan->id}/approve", [
            'approved_amount' => 8000.00,
        ]);

        $response->assertStatus(200);

        $approvedLoan = Loan::find($loan->id);
        $calculation = $this->loanService->calculate($this->borrower, 8000.00, 60);
        $agreementData = app(LoanAgreementService::class)->data($approvedLoan, $calculation, $approvedLoan->repayment_date);
        $html = view('pdf.loan-agreement', $agreementData)->render();

        $this->assertEquals(8000.00, (float) $approvedLoan->approved_amount);
        $this->assertNotNull($approvedLoan->repayment_date);
        Storage::disk((string) config('loan.agreement.disk'))->assertExists($approvedLoan->agreement_path);
        $this->assertStringContainsString(number_format($calculation->principal, 2), $html);
        $this->assertStringContainsString(number_format($calculation->interestAmount, 2), $html);
        $this->assertStringContainsString(number_format($calculation->platformFee, 2), $html);
        $this->assertStringContainsString(number_format($calculation->totalRepayment, 2), $html);
    }

    public function test_admin_can_reject_loan(): void
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

        $response = $this->postJson("/api/loans/admin/{$loan->id}/reject", [
            'reason' => 'Insufficient documentation.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.loan.status', 'cancelled');

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'rejection_reason' => 'Insufficient documentation.',
        ]);
    }

    public function test_reject_requires_reason(): void
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

        $this->postJson("/api/loans/admin/{$loan->id}/reject", [])->assertStatus(422);
    }

    public function test_cannot_approve_already_approved_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 60,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/loans/admin/{$loan->id}/approve")->assertStatus(422);
    }

    // ─── Admin Listing Tests ─────────────────────────────────────────

    public function test_admin_can_view_pending_loans(): void
    {
        Loan::create([
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

        $response = $this->getJson('/api/loans/admin/pending');
        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    // ─── RBAC Tests ──────────────────────────────────────────────────

    public function test_borrower_cannot_access_admin_loan_routes(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->getJson('/api/loans/admin/pending')->assertStatus(403);
    }

    public function test_compliance_officer_cannot_request_loans(): void
    {
        $officer = User::factory()->active()->create(['trust_score' => 60.00]);
        $this->assignComplianceOfficerRole($officer);

        Sanctum::actingAs($officer);

        $this->postJson('/api/loans/request', [
            'requested_amount' => (float) config('loans.min_amount'),
            'loan_term_days' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ])->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_loan_routes(): void
    {
        $this->getJson('/api/loans')->assertStatus(401);
        $this->postJson('/api/loans/request')->assertStatus(401);
        $this->getJson('/api/loans/admin/pending')->assertStatus(401);
    }

    // ─── DTO Tests ───────────────────────────────────────────────────

    public function test_loan_request_data_from_array(): void
    {
        $dto = LoanRequestData::fromArray([
            'borrower_id' => 1,
            'requested_amount' => 5000,
            'loan_term_days' => 60,
            'purpose' => 'Home repairs',
            'agreement_read' => true,
            'agreement_terms' => true,
            'electronic_documents' => true,
            'agreement_version' => '1.0',
            'ip_address' => '203.0.113.20',
            'user_agent' => 'QuickShare-API',
        ]);

        $this->assertEquals(1, $dto->borrowerId);
        $this->assertEquals(5000.00, $dto->requestedAmount);
        $this->assertEquals(60, $dto->loanTermDays);
        $this->assertEquals('Home repairs', $dto->purpose);
        $this->assertTrue($dto->agreementRead);
        $this->assertTrue($dto->agreementTermsAccepted);
        $this->assertTrue($dto->electronicDocumentsConsented);
        $this->assertSame('1.0', $dto->agreementVersion);
        $this->assertSame('203.0.113.20', $dto->ipAddress);
        $this->assertSame('QuickShare-API', $dto->userAgent);
    }

    public function test_loan_request_data_to_array(): void
    {
        $dto = new LoanRequestData(
            borrowerId: 1,
            requestedAmount: 5000.00,
            loanTermDays: 60,
            agreementRead: true,
            agreementTermsAccepted: true,
            electronicDocumentsConsented: true,
            agreementVersion: '1.0',
            ipAddress: '203.0.113.20',
            userAgent: 'QuickShare-API',
        );

        $arr = $dto->toArray();

        $this->assertEquals(1, $arr['borrower_id']);
        $this->assertEquals(5000.00, $arr['requested_amount']);
        $this->assertEquals(60, $arr['loan_term_days']);
        $this->assertNull($arr['purpose']);
        $this->assertTrue($arr['agreement_read']);
        $this->assertTrue($arr['agreement_terms']);
        $this->assertTrue($arr['electronic_documents']);
        $this->assertSame('1.0', $arr['agreement_version']);
        $this->assertSame('203.0.113.20', $arr['ip_address']);
        $this->assertSame('QuickShare-API', $arr['user_agent']);
    }

    // ─── Model Tests ─────────────────────────────────────────────────

    public function test_loan_funding_progress_calculation(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'funded_amount' => 6000,
            'loan_term_days' => 60,
            'status' => 'partially_funded',
            'submitted_at' => now(),
        ]);

        $this->assertEquals(60.00, $loan->funding_progress);
        $this->assertEquals(4000.00, $loan->remaining_funding);
    }

    public function test_loan_status_helper_methods(): void
    {
        $loan = new Loan(['status' => 'draft']);
        $this->assertTrue($loan->isDraft());
        $this->assertTrue($loan->isCancellable());

        $loan->status = 'pending_review';
        $this->assertTrue($loan->isPendingReview());
        $this->assertTrue($loan->isApprovable());
        $this->assertTrue($loan->isCancellable());

        $loan->status = 'marketplace';
        $this->assertTrue($loan->isOnMarketplace());
        $this->assertFalse($loan->isCancellable());

        $loan->status = 'funded';
        $this->assertTrue($loan->isFunded());
        $this->assertTrue($loan->isDisbursable());

        $loan->status = 'active';
        $this->assertTrue($loan->isActive());

        $loan->status = 'completed';
        $this->assertTrue($loan->isCompleted());

        $loan->status = 'defaulted';
        $this->assertTrue($loan->isDefaulted());
    }
}
