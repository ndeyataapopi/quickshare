<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Mail\LoanAgreementMail;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanAgreementPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $borrower;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake((string) config('loan.agreement.disk'));
        Mail::fake();

        $this->borrower = User::factory()->active()->create([
            'trust_score' => 55.00,
            'email_verified_at' => now(),
        ]);
        $this->assignClientRole($this->borrower);
        KycSubmission::factory()->approved()->create(['user_id' => $this->borrower->id]);
    }

    public function test_loan_form_displays_agreement_preview_modal_and_acceptance_controls(): void
    {
        $response = $this->actingAs($this->borrower)->get(route('client.loans.create'));

        $response->assertOk()
            ->assertSee('View Loan Agreement')
            ->assertSee('id="agreementModal"', false)
            ->assertSee('id="agreementPdf"', false)
            ->assertSee('I have read the agreement.')
            ->assertSee('I agree to the terms.')
            ->assertSee('I consent to electronic documents.')
            ->assertSee('name="agreement_read"', false)
            ->assertSee('name="agreement_terms"', false)
            ->assertSee('name="electronic_documents"', false)
            ->assertSee('name="agreement_version"', false)
            ->assertSee('id="submitApplicationBtn" disabled', false)
            ->assertSee('acceptanceCheckboxes.every(checkbox => checkbox.checked)', false)
            ->assertSee(route('client.loans.agreement-preview'), false);
    }

    public function test_borrower_can_preview_calculated_agreement_as_embedded_pdf(): void
    {
        $response = $this->actingAs($this->borrower)->get(route('client.loans.agreement-preview', [
            'amount' => (float) config('loans.min_amount'),
            'repayment_period' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="loan-agreement-preview.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseCount((new Loan)->getTable(), 0);
    }

    public function test_agreement_preview_validates_tier_amount_and_duration(): void
    {
        $this->actingAs($this->borrower)->getJson(route('client.loans.agreement-preview', [
            'amount' => (float) config('loan.trust_tiers.silver.maximum_loan') + 1,
            'repayment_period' => max(config('loan.trust_tiers.silver.allowed_durations')) + 1,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'repayment_period']);
    }

    public function test_unauthenticated_user_cannot_preview_agreement(): void
    {
        $this->get(route('client.loans.agreement-preview', [
            'amount' => (float) config('loans.min_amount'),
            'repayment_period' => max(config('loan.trust_tiers.silver.allowed_durations')),
        ]))->assertRedirect(route('login'));
    }

    public function test_final_web_submission_requires_current_agreement_consent(): void
    {
        $this->actingAs($this->borrower)->from(route('client.loans.create'))->post(route('client.loans.store'), [
            'amount' => (float) config('loans.min_amount'),
            'purpose' => 'Education',
            'repayment_period' => max(config('loan.trust_tiers.silver.allowed_durations')),
            'agreement_version' => 'outdated',
        ])->assertRedirect(route('client.loans.create'))
            ->assertSessionHasErrors([
                'agreement_read',
                'agreement_terms',
                'electronic_documents',
                'agreement_version',
            ]);

        $this->assertDatabaseCount('loans', 0);
        Mail::assertNothingQueued();
    }

    public function test_final_web_submission_accepts_string_repayment_period(): void
    {
        $this->actingAs($this->borrower)
            ->post(route('client.loans.store'), [
                'amount' => (float) config('loans.min_amount'),
                'purpose' => 'Education',
                'repayment_period' => (string) max(config('loan.trust_tiers.silver.allowed_durations')),
                'agreement_read' => '1',
                'agreement_terms' => '1',
                'electronic_documents' => '1',
                'agreement_version' => (string) config('loan.agreement.version'),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('loans', 1);
    }

    public function test_final_web_submission_stores_agreement_configuration_and_request_audit(): void
    {
        $response = $this->actingAs($this->borrower)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'QuickShare-Browser')
            ->post(route('client.loans.store'), [
                'amount' => (float) config('loans.min_amount'),
                'purpose' => 'Education',
                'repayment_period' => max(config('loan.trust_tiers.silver.allowed_durations')),
                'agreement_read' => '1',
                'agreement_terms' => '1',
                'electronic_documents' => '1',
                'agreement_version' => (string) config('loan.agreement.version'),
            ]);

        $loan = Loan::firstOrFail();
        $this->assertSame('Education', $loan->purpose);
        $response->assertRedirect(route('client.loans.show', $loan));
        Storage::disk((string) config('loan.agreement.disk'))->assertExists($loan->agreement_path);
        $this->assertSame((string) config('loan.agreement.version'), $loan->agreement_version);
        $this->assertSame('silver', $loan->configuration_snapshot['trust_tier']['name']);
        $this->assertSame(config('loan.agreement.terms'), $loan->configuration_snapshot['agreement']['terms']);
        $this->assertSame([
            'agreement_read' => true,
            'agreement_terms_accepted' => true,
            'electronic_documents_consented' => true,
        ], $loan->agreement_consent);
        $this->assertSame('203.0.113.10', $loan->agreement_ip_address);
        $this->assertSame('QuickShare-Browser', $loan->agreement_user_agent);
        $this->assertNotNull($loan->agreement_consented_at);
        $this->assertNotNull($loan->agreement_generated_at);
        Mail::assertQueued(LoanAgreementMail::class, fn (LoanAgreementMail $mail): bool => $mail->hasTo($this->borrower->email) && $mail->loan->is($loan));
    }
}
