<?php

namespace Tests\Feature\Repayments;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepaymentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected RepaymentService $service;
    protected User $borrower;
    protected User $lender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(RepaymentService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);

        $this->lender = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($this->lender);
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

    protected function createPendingRepayment(Loan $loan): Repayment
    {
        return Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'principal' => 10000,
            'interest' => 246,
            'platform_fee' => 300,
            'penalty' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);
    }

    // ─── Service: submitRepaymentRequest ──────────────────────────────

    public function test_submit_repayment_request_sets_pending_approval_status(): void
    {
        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);
        $repayment = $this->createPendingRepayment($loan);

        $result = $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
            'TRX-REF-001',
        );

        $this->assertDatabaseHas('repayments', [
            'id' => $repayment->id,
            'status' => 'pending_approval',
            'payment_method' => 'eft',
            'payment_proof_path' => 'repayment-proofs/proof.pdf',
            'external_reference' => 'TRX-REF-001',
        ]);

        $this->assertTrue($result['repayments']->first()->isPendingApproval());
    }

    public function test_submit_repayment_request_creates_incoming_disbursement(): void
    {
        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);
        $repayment = $this->createPendingRepayment($loan);

        $result = $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'mobile_wallet',
            'repayment-proofs/proof.jpg',
            'WALLET-REF-002',
        );

        $this->assertCount(1, $result['disbursements']);

        $disbursement = $result['disbursements']->first();
        $this->assertEquals('incoming', $disbursement->direction);
        $this->assertEquals('awaiting_approval', $disbursement->status);
        $this->assertEquals($loan->id, $disbursement->loan_id);
        $this->assertEquals(10546, (float) $disbursement->gross_amount);
        $this->assertEquals(10546, (float) $disbursement->net_amount);
        $this->assertEquals('mobile_wallet', $disbursement->payment_method);
        $this->assertEquals('repayment-proofs/proof.jpg', $disbursement->payment_proof_path);
        $this->assertEquals('WALLET-REF-002', $disbursement->external_reference);
    }

    public function test_submit_repayment_request_does_not_reduce_loan_balance(): void
    {
        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);
        $repayment = $this->createPendingRepayment($loan);

        $originalFundedAmount = (float) $loan->funded_amount;

        $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
        );

        $loan->refresh();
        $this->assertEquals($originalFundedAmount, (float) $loan->funded_amount);
        $this->assertEquals('active', $loan->status);
    }

    public function test_submit_repayment_request_does_not_create_lender_repayments(): void
    {
        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);
        $repayment = $this->createPendingRepayment($loan);

        $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
        );

        $this->assertEquals(0, LenderRepayment::where('repayment_id', $repayment->id)->count());
    }

    public function test_submit_multiple_repayments_in_one_request(): void
    {
        $loan1 = $this->createActiveLoan();
        $this->createFunding($loan1, 10000);
        $repayment1 = $this->createPendingRepayment($loan1);

        $loan2 = $this->createActiveLoan();
        $this->createFunding($loan2, 10000);
        $repayment2 = $this->createPendingRepayment($loan2);

        $result = $this->service->submitRepaymentRequest(
            [$repayment1->id, $repayment2->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
            'MULTI-REF-003',
        );

        $this->assertCount(2, $result['repayments']);
        $this->assertCount(2, $result['disbursements']);

        $this->assertDatabaseHas('repayments', ['id' => $repayment1->id, 'status' => 'pending_approval']);
        $this->assertDatabaseHas('repayments', ['id' => $repayment2->id, 'status' => 'pending_approval']);

        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan1->id,
            'direction' => 'incoming',
            'status' => 'awaiting_approval',
        ]);
        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan2->id,
            'direction' => 'incoming',
            'status' => 'awaiting_approval',
        ]);
    }

    public function test_submit_repayment_request_rejects_other_borrower_repayments(): void
    {
        $otherBorrower = User::factory()->active()->create();
        $this->assignClientRole($otherBorrower);

        $loan = $this->createActiveLoan(['borrower_id' => $otherBorrower->id]);
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $otherBorrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('No eligible repayments found');

        $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
        );
    }

    public function test_submit_repayment_request_rejects_inactive_loan(): void
    {
        $loan = $this->createActiveLoan(['status' => 'funded']);
        $repayment = $this->createPendingRepayment($loan);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Loan is not active');

        $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
        );
    }

    public function test_submit_repayment_request_rejects_already_paid_repayment(): void
    {
        $loan = $this->createActiveLoan();
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
        $this->expectExceptionMessage('No eligible repayments found');

        $this->service->submitRepaymentRequest(
            [$repayment->id],
            $this->borrower,
            'eft',
            'repayment-proofs/proof.pdf',
        );
    }

    // ─── API: POST /api/repayments ────────────────────────────────────

    public function test_api_submit_repayment_request_with_file_upload(): void
    {
        Storage::fake('public');

        $loan = $this->createActiveLoan();
        $this->createFunding($loan, 10000);
        $repayment = $this->createPendingRepayment($loan);

        Sanctum::actingAs($this->borrower);

        $response = $this->post('/api/repayments', [
            'repayment_ids' => [$repayment->id],
            'payment_method' => 'eft',
            'external_reference' => 'API-REF-001',
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 1000, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Repayment request submitted for approval.');

        $this->assertDatabaseHas('repayments', [
            'id' => $repayment->id,
            'status' => 'pending_approval',
        ]);

        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'status' => 'awaiting_approval',
        ]);

        Storage::disk('public')->assertExists(
            Repayment::find($repayment->id)->payment_proof_path
        );
    }

    public function test_api_submit_repayment_request_validates_required_fields(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/repayments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['repayment_ids', 'payment_method', 'proof_of_payment']);
    }

    public function test_api_submit_repayment_request_requires_valid_payment_method(): void
    {
        Storage::fake('public');

        $loan = $this->createActiveLoan();
        $repayment = $this->createPendingRepayment($loan);

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/repayments', [
            'repayment_ids' => [$repayment->id],
            'payment_method' => 'invalid_method',
        ], [
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 1000, 'application/pdf'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_api_submit_repayment_request_rejects_other_borrower(): void
    {
        Storage::fake('public');

        $otherBorrower = User::factory()->active()->create();
        $this->assignClientRole($otherBorrower);

        $loan = $this->createActiveLoan(['borrower_id' => $otherBorrower->id]);
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $otherBorrower->id,
            'amount' => 10546,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/repayments', [
            'repayment_ids' => [$repayment->id],
            'payment_method' => 'eft',
        ], [
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 1000, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }

    // ─── Model Tests ──────────────────────────────────────────────────

    public function test_repayment_is_pending_approval_helper(): void
    {
        $loan = $this->createActiveLoan();
        $repayment = Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending_approval',
            'due_date' => now()->addDays(30),
            'transaction_reference' => Repayment::generateReference(),
        ]);

        $this->assertTrue($repayment->isPendingApproval());
        $this->assertFalse($repayment->isPending());
        $this->assertFalse($repayment->isPaid());
    }

    public function test_repayment_pending_approval_scope(): void
    {
        $loan = $this->createActiveLoan();

        Repayment::create([
            'loan_id' => $loan->id,
            'borrower_id' => $this->borrower->id,
            'amount' => 10546,
            'status' => 'pending_approval',
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

        $this->assertEquals(1, Repayment::pendingApproval()->count());
        $this->assertEquals(1, Repayment::pending()->count());
    }

    public function test_disbursement_transaction_incoming_scope(): void
    {
        $loan = $this->createActiveLoan();

        DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'gross_amount' => 5000,
            'platform_fee' => 0,
            'net_amount' => 5000,
            'status' => 'awaiting_approval',
            'transaction_reference' => DisbursementTransaction::generateReference(),
        ]);

        DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'outgoing',
            'gross_amount' => 10000,
            'platform_fee' => 300,
            'net_amount' => 9700,
            'status' => 'disbursed',
            'transaction_reference' => DisbursementTransaction::generateReference(),
        ]);

        $this->assertEquals(1, DisbursementTransaction::incoming()->count());
        $this->assertEquals(1, DisbursementTransaction::outgoing()->count());
    }

    public function test_disbursement_transaction_awaiting_approval_scope(): void
    {
        $loan = $this->createActiveLoan();

        DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'incoming',
            'gross_amount' => 5000,
            'platform_fee' => 0,
            'net_amount' => 5000,
            'status' => 'awaiting_approval',
            'transaction_reference' => DisbursementTransaction::generateReference(),
        ]);

        DisbursementTransaction::create([
            'loan_id' => $loan->id,
            'direction' => 'outgoing',
            'gross_amount' => 10000,
            'platform_fee' => 300,
            'net_amount' => 9700,
            'status' => 'disbursed',
            'transaction_reference' => DisbursementTransaction::generateReference(),
        ]);

        $this->assertEquals(1, DisbursementTransaction::awaitingApproval()->count());
    }
}
