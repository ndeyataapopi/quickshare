<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Loans\Mail\LoanAgreementMail;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanAgreementViewerTest extends TestCase
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

    protected function createLoan(string $agreementPath = null, float $trustScore = 70.00): Loan
    {
        $borrower = User::factory()->active()->create([
            'email_verified_at' => now(),
            'trust_score' => $trustScore,
        ]);
        $this->assignClientRole($borrower);

        return Loan::factory()->create([
            'borrower_id' => $borrower->id,
            'risk_score' => $trustScore,
            'agreement_path' => $agreementPath,
            'agreement_version' => '1.0',
            'configuration_snapshot' => [
                'currency' => config('loans.currency'),
                'currency_symbol' => config('loans.currency_symbol'),
                'minimum_borrow_score' => 30,
                'trust_tier' => [
                    'name' => 'gold',
                ],
            ],
        ]);
    }

    public function test_admin_can_view_loan_agreement_page(): void
    {
        $loan = $this->createLoan();

        $this->actingAs($this->admin)
            ->get(route('admin.loans.agreement', $loan))
            ->assertOk()
            ->assertSee('Loan Summary')
            ->assertSee('Configuration Snapshot')
            ->assertSee($loan->reference)
            ->assertSee('Gold');
    }

    public function test_admin_can_download_loan_agreement(): void
    {
        $disk = (string) config('loan.agreement.disk');
        Storage::fake($disk);

        $path = 'loan-agreements/QS-DLTEST.pdf';
        Storage::disk($disk)->put($path, 'PDF-CONTENT');

        $loan = $this->createLoan($path);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.loans.agreement.download', $loan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertEquals('PDF-CONTENT', $response->getContent());
    }

    public function test_admin_can_view_loan_agreement_pdf_inline(): void
    {
        $disk = (string) config('loan.agreement.disk');
        Storage::fake($disk);

        $path = 'loan-agreements/QS-INTEST.pdf';
        Storage::disk($disk)->put($path, 'PDF-INLINE');

        $loan = $this->createLoan($path);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.loans.agreement.pdf', $loan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('content-disposition'));
        $this->assertEquals('PDF-INLINE', $response->getContent());
    }

    public function test_admin_can_resend_loan_agreement_email(): void
    {
        $disk = (string) config('loan.agreement.disk');
        Storage::fake($disk);
        Mail::fake();

        $path = 'loan-agreements/QS-RESEND.pdf';
        Storage::disk($disk)->put($path, 'PDF-RESEND');

        $loan = $this->createLoan($path);

        $this->actingAs($this->admin)
            ->post(route('admin.loans.agreement.resend', $loan))
            ->assertRedirect();

        Mail::assertQueued(LoanAgreementMail::class, function (LoanAgreementMail $mail) use ($loan): bool {
            return $mail->hasTo($loan->borrower->email) && $mail->loan->is($loan);
        });
    }

    public function test_non_admin_cannot_view_loan_agreement(): void
    {
        $loan = $this->createLoan();
        $client = User::factory()->active()->create([
            'email_verified_at' => now(),
            'trust_score' => 70,
        ]);
        $this->assignClientRole($client);

        $this->actingAs($client)
            ->get(route('admin.loans.agreement', $loan))
            ->assertForbidden();
    }

    public function test_missing_agreement_download_returns_404(): void
    {
        $loan = $this->createLoan('loan-agreements/missing.pdf');

        $this->actingAs($this->admin)
            ->get(route('admin.loans.agreement.download', $loan))
            ->assertNotFound();
    }
}
