<?php

namespace Tests\Feature\Loans;

use App\Models\User;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisbursementTest extends TestCase
{
    use RefreshDatabase;

    protected DisbursementService $service;
    protected User $borrower;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(DisbursementService::class);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);

        $this->admin = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->assignAdminRole($this->admin);
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

    // ─── Initiate Disbursement Tests ─────────────────────────────────

    public function test_disbursement_initiated_when_loan_funded(): void
    {
        $loan = $this->createFundedLoan();

        $disbursement = $this->service->initiateDisbursement($loan);

        $this->assertNotNull($disbursement->id);
        $this->assertEquals($loan->id, $disbursement->loan_id);
        $this->assertEquals('awaiting_disbursement', $disbursement->status);
        $this->assertEquals(10000.00, $disbursement->gross_amount);
        $this->assertEquals(300.00, $disbursement->platform_fee);
        $this->assertEquals(10000.00, $disbursement->net_amount); // Borrower receives full principal
        $this->assertNotNull($disbursement->transaction_reference);
        $this->assertStringStartsWith('DISB-', $disbursement->transaction_reference);
    }

    public function test_disbursement_creates_ledger_entries(): void
    {
        $loan = $this->createFundedLoan();

        $disbursement = $this->service->initiateDisbursement($loan);

        $this->assertNotNull($disbursement->ledger_entries);
        $this->assertCount(4, $disbursement->ledger_entries);
        
        // Check ledger structure
        $ledger = $disbursement->ledger_entries;
        $this->assertEquals('loan_funding_receivable', $ledger[0]['account']);
        $this->assertEquals('platform_fee_income', $ledger[1]['account']);
        $this->assertEquals('loan_disbursement_payable', $ledger[2]['account']);
        $this->assertEquals('loan_receivable', $ledger[3]['account']);
    }

    public function test_disbursement_updates_loan_status(): void
    {
        $loan = $this->createFundedLoan();
        $this->assertEquals('funded', $loan->status);

        $this->service->initiateDisbursement($loan);
        $loan->refresh();

        $this->assertEquals('awaiting_disbursement', $loan->status);
    }

    public function test_cannot_disburse_unfunded_loan(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'interest_rate' => 15,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'loan_term_days' => 60,
            'status' => 'marketplace', // Not funded
            'submitted_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Loan cannot be disbursed');

        $this->service->initiateDisbursement($loan);
    }

    public function test_cannot_disburse_already_disbursed_loan(): void
    {
        $loan = $this->createFundedLoan();

        // First disbursement
        $this->service->initiateDisbursement($loan);

        // Try second disbursement
        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Disbursement already initiated');

        $this->service->initiateDisbursement($loan);
    }

    public function test_cannot_disburse_if_net_amount_zero_or_negative(): void
    {
        $loan = $this->createFundedLoan([
            'approved_amount' => 0,
            'requested_amount' => 0,
            'funded_amount' => 0,
        ]);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Net disbursement amount must be positive');

        $this->service->initiateDisbursement($loan);
    }

    // ─── Process Disbursement Tests ──────────────────────────────────

    public function test_disbursement_processed_successfully(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        $processed = $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);

        $this->assertEquals('pending_borrower_confirmation', $processed->status);
        $this->assertNotNull($processed->processed_at);
        $this->assertEquals('TRX-TEST123', $processed->external_reference);
        $this->assertEquals('bank_transfer', $processed->payment_method);
        $this->assertEquals('disbursement-proofs/test.pdf', $processed->payment_proof_path);

        // Borrower confirms receipt
        $confirmed = $this->service->confirmReceipt($processed->fresh());

        $this->assertEquals('disbursed', $confirmed->status);
        $this->assertNotNull($confirmed->borrower_confirmed_at);

        // Loan should be updated to active
        $loan->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);
    }

    public function test_cannot_process_non_awaiting_disbursement(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);

        // Try to process again
        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Disbursement cannot be processed');

        $this->service->processDisbursement($disbursement->fresh());
    }

    // ─── Retry Tests ─────────────────────────────────────────────────

    public function test_failed_disbursement_can_be_retried(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        // Simulate failure
        $disbursement->update([
            'status' => 'failed',
            'failure_reason' => 'Bank timeout',
            'retry_count' => 0,
        ]);

        $this->assertTrue($disbursement->canRetry());

        $newDisbursement = $this->service->retryDisbursement($disbursement);

        $this->assertEquals('retried', $disbursement->fresh()->status);
        $this->assertEquals('awaiting_disbursement', $newDisbursement->status);
        $this->assertEquals(1, $newDisbursement->retry_count);
        $this->assertNotNull($newDisbursement->transaction_reference);
    }

    public function test_disbursement_cannot_retry_after_max_attempts(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        $disbursement->update([
            'status' => 'failed',
            'retry_count' => 3,
        ]);

        $this->assertFalse($disbursement->canRetry());

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Disbursement cannot be retried');

        $this->service->retryDisbursement($disbursement);
    }

    public function test_disbursement_cannot_retry_if_not_failed(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Disbursement cannot be retried');

        $this->service->retryDisbursement($disbursement);
    }

    // ─── Reconciliation Tests ──────────────────────────────────────

    public function test_disbursement_can_be_reconciled(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);
        $this->service->confirmReceipt($disbursement->fresh());

        $reconciled = $this->service->reconcile(
            $disbursement->fresh(),
            'admin@quickshare.com',
            ['bank_statement_line' => 12345],
        );

        $this->assertNotNull($reconciled->reconciled_at);
        $this->assertEquals('admin@quickshare.com', $reconciled->reconciled_by);
        $this->assertNotNull($reconciled->reconciliation_data);
        $this->assertTrue($reconciled->isReconciled());
    }

    public function test_cannot_reconcile_non_disbursed_transaction(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Only disbursed transactions can be reconciled');

        $this->service->reconcile($disbursement, 'admin@quickshare.com');
    }

    // ─── API Tests ─────────────────────────────────────────────────────

    public function test_borrower_can_view_loan_disbursements(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson("/api/loans/{$loan->id}/disbursements");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.disbursements')
            ->assertJsonPath('data.disbursements.0.id', $disbursement->id);
    }

    public function test_borrower_cannot_view_other_loan_disbursements(): void
    {
        $otherBorrower = User::factory()->active()->create();
        $this->assignClientRole($otherBorrower);

        $loan = $this->createFundedLoan(['borrower_id' => $otherBorrower->id]);
        $this->service->initiateDisbursement($loan);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson("/api/loans/{$loan->id}/disbursements");

        // Should return 404 (not found) since loan doesn't belong to this borrower
        $response->assertStatus(404);
    }

    public function test_admin_can_view_any_disbursement(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/loans/disbursements/{$disbursement->id}");

        $response->assertOk()
            ->assertJsonPath('data.disbursement.id', $disbursement->id);
    }

    public function test_admin_can_view_pending_disbursements(): void
    {
        $loan1 = $this->createFundedLoan();
        $loan2 = $this->createFundedLoan();
        
        $this->service->initiateDisbursement($loan1);
        $this->service->initiateDisbursement($loan2);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/loans/disbursements/pending');

        $response->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_admin_can_retry_failed_disbursement(): void
    {
        Queue::fake();
        
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        
        $disbursement->update(['status' => 'failed', 'retry_count' => 0]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/loans/disbursements/{$disbursement->id}/retry");

        $response->assertOk()
            ->assertJsonPath('data.new_disbursement.status', 'awaiting_disbursement')
            ->assertJsonPath('data.new_disbursement.retry_count', 1);
    }

    public function test_admin_can_reconcile_disbursement(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);
        $this->service->confirmReceipt($disbursement->fresh());

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/loans/disbursements/{$disbursement->id}/reconcile", [
            'reconciliation_data' => ['bank_ref' => 'BANK123'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.disbursement.reconciled_by', $this->admin->email)
            ->assertJsonPath('data.disbursement.reconciliation_data.bank_ref', 'BANK123');
    }

    public function test_admin_can_view_reconciliation_report(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);
        $this->service->confirmReceipt($disbursement->fresh());
        $this->service->reconcile($disbursement->fresh(), 'admin@test.com');

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/loans/disbursements/reconciliation-report');

        $response->assertOk()
            ->assertJsonPath('data.total_disbursed', 1)
            ->assertJsonPath('data.reconciled_count', 1)
            ->assertJsonPath('data.unreconciled_count', 0);
    }

    public function test_reconciliation_report_shows_correct_totals(): void
    {
        // Create and process multiple disbursements
        $loan1 = $this->createFundedLoan([
            'approved_amount' => 5000,
            'requested_amount' => 5000,
            'funded_amount' => 5000,
        ]);
        $d1 = $this->service->initiateDisbursement($loan1);
        $this->service->processDisbursement($d1, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-001',
            'payment_proof_path' => 'proofs/1.pdf',
        ]);
        $this->service->confirmReceipt($d1->fresh());

        $loan2 = $this->createFundedLoan([
            'approved_amount' => 10000,
            'requested_amount' => 10000,
            'funded_amount' => 10000,
        ]);
        $d2 = $this->service->initiateDisbursement($loan2);
        $this->service->processDisbursement($d2, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-002',
            'payment_proof_path' => 'proofs/2.pdf',
        ]);
        $this->service->confirmReceipt($d2->fresh());
        $this->service->reconcile($d2->fresh(), 'admin@test.com');

        $loan3 = $this->createFundedLoan([
            'approved_amount' => 8000,
            'requested_amount' => 8000,
            'funded_amount' => 8000,
        ]);
        $d3 = $this->service->initiateDisbursement($loan3);
        $d3->update(['status' => 'failed']);

        $report = $this->service->getReconciliationReport();

        $this->assertEquals(2, $report['total_disbursed']);
        $this->assertEquals(1, $report['reconciled_count']);
        $this->assertEquals(1, $report['unreconciled_count']);
        $this->assertEquals(1, $report['failed_count']);
        // Loan1: 5000, Loan2: 10000, Total = 15000
        $this->assertEquals(15000, $report['total_amount']);
    }

    // ─── Model Tests ─────────────────────────────────────────────────

    public function test_disbursement_transaction_scopes(): void
    {
        $loan = $this->createFundedLoan();
        $d1 = $this->service->initiateDisbursement($loan);
        
        $loan2 = $this->createFundedLoan();
        $d2 = $this->service->initiateDisbursement($loan2);
        $d2->update(['status' => 'failed', 'retry_count' => 0]);

        $this->assertEquals(1, DisbursementTransaction::awaiting()->count()); // Only d1 is awaiting, d2 is failed
        $this->assertEquals(1, DisbursementTransaction::failed()->count());
        $this->assertEquals(1, DisbursementTransaction::needsRetry()->count());
    }

    public function test_loan_has_disbursements_relationship(): void
    {
        $loan = $this->createFundedLoan();
        $this->service->initiateDisbursement($loan);

        $this->assertCount(1, $loan->fresh()->disbursements);
    }

    public function test_disbursement_reference_generation(): void
    {
        $ref1 = DisbursementTransaction::generateReference();
        $ref2 = DisbursementTransaction::generateReference();

        $this->assertNotEquals($ref1, $ref2);
        $this->assertStringStartsWith('DISB-', $ref1);
        $this->assertEquals(17, strlen($ref1)); // DISB- + 12 hex chars
    }

    // ─── Service Query Tests ─────────────────────────────────────────

    public function test_service_returns_loan_disbursements(): void
    {
        $loan = $this->createFundedLoan();
        $d1 = $this->service->initiateDisbursement($loan);
        
        // Create a retry
        $d1->update(['status' => 'failed']);
        $d2 = $this->service->retryDisbursement($d1);

        $disbursements = $this->service->getLoanDisbursements($loan->id);

        $this->assertCount(2, $disbursements);
    }

    // ─── Borrower Confirm Receipt Tests (Stage 7.1) ──────────────────

    public function test_confirm_receipt_creates_repayment_schedule(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);

        $confirmed = $this->service->confirmReceipt($disbursement->fresh());

        $this->assertEquals('disbursed', $confirmed->status);
        $this->assertNotNull($confirmed->borrower_confirmed_at);

        // Loan should be active
        $loan->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);

        // Repayment schedule should be created
        $repayment = \App\Modules\Repayments\Models\Repayment::forLoan($loan->id)->first();
        $this->assertNotNull($repayment);
        $this->assertEquals('pending', $repayment->status);
        $this->assertEquals($loan->total_repayment, $repayment->amount);
    }

    public function test_cannot_confirm_non_pending_borrower_confirmation(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        // Status is 'awaiting_disbursement', not 'pending_borrower_confirmation'
        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Disbursement is not pending borrower confirmation');

        $this->service->confirmReceipt($disbursement);
    }

    // ─── Borrower Reject Receipt Tests (Stage 7.1) ───────────────────

    public function test_borrower_can_reject_disbursement(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST123',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);

        $rejected = $this->service->rejectReceipt($disbursement->fresh(), 'Did not receive funds');

        $this->assertEquals('rejected_by_borrower', $rejected->status);
        $this->assertNotNull($rejected->borrower_rejected_at);
        $this->assertEquals('Did not receive funds', $rejected->rejection_reason);

        // Loan remains awaiting_disbursement so admin can re-initiate
        $loan->refresh();
        $this->assertEquals('awaiting_disbursement', $loan->status);

        // No repayment schedule should exist
        $repayment = \App\Modules\Repayments\Models\Repayment::forLoan($loan->id)->first();
        $this->assertNull($repayment);
    }

    public function test_borrower_can_reject_without_reason(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST456',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);

        $rejected = $this->service->rejectReceipt($disbursement->fresh());

        $this->assertEquals('rejected_by_borrower', $rejected->status);
        $this->assertNull($rejected->rejection_reason);
    }

    public function test_cannot_reject_non_pending_borrower_confirmation(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);

        // Status is 'awaiting_disbursement', not 'pending_borrower_confirmation'
        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('Disbursement is not pending borrower confirmation');

        $this->service->rejectReceipt($disbursement);
    }

    public function test_rejected_disbursement_transaction_is_rejected_by_borrower(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-TEST789',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);

        $rejected = $this->service->rejectReceipt($disbursement->fresh(), 'Wrong amount');

        $this->assertTrue($rejected->isRejectedByBorrower());
    }

    public function test_rejected_disbursement_scope_works(): void
    {
        $loan = $this->createFundedLoan();
        $disbursement = $this->service->initiateDisbursement($loan);
        $this->service->processDisbursement($disbursement, [
            'payment_method' => 'bank_transfer',
            'external_reference' => 'TRX-SCOPE-TEST',
            'payment_proof_path' => 'disbursement-proofs/test.pdf',
        ]);
        $this->service->rejectReceipt($disbursement->fresh());

        $rejectedCount = DisbursementTransaction::rejectedByBorrower()->count();
        $this->assertEquals(1, $rejectedCount);
    }
}
