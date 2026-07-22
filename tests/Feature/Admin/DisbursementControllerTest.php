<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisbursementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $borrower;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->active()->create();
        $this->assignAdminRole($this->admin);

        $this->borrower = User::factory()->active()->create();
        $this->assignClientRole($this->borrower);
    }

    protected function createFundedLoan(array $overrides = []): Loan
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
            'status' => 'funded',
            'risk_score' => 65.00,
            'submitted_at' => now(),
            'approved_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_initiate_disbursement(): void
    {
        $loan = $this->createFundedLoan();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.disbursements.disburse', $loan));

        $response->assertRedirect(route('admin.disbursements.show', $loan))
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals('awaiting_disbursement', $loan->status);
        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'status' => 'awaiting_disbursement',
        ]);
    }

    public function test_admin_can_confirm_disbursement_with_proof(): void
    {
        Storage::fake('private');

        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.disbursements.confirm', $loan), [
                'payment_method' => 'bank_transfer',
                'external_reference' => 'TRX-TEST12345',
                'payment_proof' => $file,
            ]);

        $response->assertRedirect(route('admin.disbursements.show', $loan))
            ->assertSessionHas('success');

        $loan->refresh();
        $transaction = $loan->disbursements()->latest()->first();
        $this->assertEquals('awaiting_disbursement', $loan->status);
        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'status' => 'pending_borrower_confirmation',
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST12345',
        ]);

        $this->assertNotNull($transaction->payment_proof_path);
        Storage::disk('private')->assertExists($transaction->payment_proof_path);
    }

    public function test_confirm_disbursement_requires_payment_proof(): void
    {
        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.disbursements.confirm', $loan), [
                'payment_method' => 'bank_transfer',
                'external_reference' => 'TRX-TEST12345',
            ]);

        $response->assertSessionHasErrors('payment_proof');
    }

    public function test_confirm_disbursement_requires_external_reference(): void
    {
        Storage::fake('private');

        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.disbursements.confirm', $loan), [
                'payment_method' => 'bank_transfer',
                'external_reference' => '',
                'payment_proof' => $file,
            ]);

        $response->assertSessionHasErrors('external_reference');
    }

    public function test_borrower_can_confirm_receipt(): void
    {
        Storage::fake('private');

        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $transaction = $loan->disbursements()->latest()->first();
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        $proofPath = $file->store('disbursement-proofs', 'private');

        $service->processDisbursement($transaction, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST12345',
            'payment_proof_path' => $proofPath,
        ]);

        $response = $this->actingAs($this->borrower)
            ->post(route('client.loans.disbursement.confirm', $loan));

        $response->assertRedirect(route('client.dashboard'))
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'status' => 'disbursed',
        ]);

        $transaction->refresh();
        $this->assertNotNull($transaction->borrower_confirmed_at);
    }

    public function test_borrower_cannot_confirm_non_pending_disbursement(): void
    {
        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $response = $this->actingAs($this->borrower)
            ->post(route('client.loans.disbursement.confirm', $loan));

        $response->assertRedirect()
            ->assertSessionHas('error');

        $loan->refresh();
        $this->assertEquals('awaiting_disbursement', $loan->status);
    }

    public function test_admin_cannot_disburse_non_funded_loan(): void
    {
        $loan = $this->createFundedLoan(['status' => 'marketplace']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.disbursements.disburse', $loan));

        $response->assertRedirect()
            ->assertSessionHas('error');

        $loan->refresh();
        $this->assertEquals('marketplace', $loan->status);
        $this->assertDatabaseMissing('disbursement_transactions', ['loan_id' => $loan->id]);
    }

    public function test_borrower_can_reject_disbursement(): void
    {
        Storage::fake('private');

        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $transaction = $loan->disbursements()->latest()->first();
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        $proofPath = $file->store('disbursement-proofs', 'private');

        $service->processDisbursement($transaction, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-REJECT-001',
            'payment_proof_path' => $proofPath,
        ]);

        $response = $this->actingAs($this->borrower)
            ->post(route('client.loans.disbursement.reject', $loan), [
                'reason' => 'Did not receive funds',
            ]);

        $response->assertRedirect(route('client.dashboard'))
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals('awaiting_disbursement', $loan->status);
        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'status' => 'rejected_by_borrower',
        ]);

        $transaction->refresh();
        $this->assertNotNull($transaction->borrower_rejected_at);
        $this->assertEquals('Did not receive funds', $transaction->rejection_reason);
    }

    public function test_borrower_can_reject_without_reason(): void
    {
        Storage::fake('private');

        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $transaction = $loan->disbursements()->latest()->first();
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');
        $proofPath = $file->store('disbursement-proofs', 'private');

        $service->processDisbursement($transaction, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-REJECT-002',
            'payment_proof_path' => $proofPath,
        ]);

        $response = $this->actingAs($this->borrower)
            ->post(route('client.loans.disbursement.reject', $loan));

        $response->assertRedirect(route('client.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'status' => 'rejected_by_borrower',
        ]);
    }

    public function test_borrower_cannot_reject_non_pending_disbursement(): void
    {
        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        // Transaction is 'awaiting_disbursement', not 'pending_borrower_confirmation'
        $response = $this->actingAs($this->borrower)
            ->post(route('client.loans.disbursement.reject', $loan));

        $response->assertRedirect()
            ->assertSessionHas('error');

        $loan->refresh();
        $this->assertEquals('awaiting_disbursement', $loan->status);
    }
}
