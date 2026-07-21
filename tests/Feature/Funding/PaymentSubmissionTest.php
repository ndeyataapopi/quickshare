<?php

namespace Tests\Feature\Funding;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Funding\Services\FundingService;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected FundingService $service;
    protected User $lender;
    protected User $borrower;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(FundingService::class);

        $this->lender = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($this->lender);
        KycSubmission::factory()->approved()->create(['user_id' => $this->lender->id]);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);
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

    protected function createPendingFundingTransaction(Loan $loan): FundingTransaction
    {
        return FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $this->lender->id,
            'amount' => 5000,
            'interest_rate' => 8.00,
            'expected_return' => 5400,
            'status' => 'pending',
            'transaction_reference' => FundingTransaction::generateReference(),
            'payment_reference' => 'QS-LOAN-' . $loan->id . '-1',
        ]);
    }

    protected function fakeProofFile(): File
    {
        return File::create('proof_of_payment.pdf', 1024, 'application/pdf');
    }

    protected function validPaymentData(): array
    {
        return [
            'payment_method' => 'eft',
            'payment_method_detail' => 'fnb_namibia',
            'payment_reference' => 'QS-LOAN-1-1',
            'reference_number' => 'REF123456',
            'transaction_number' => 'TXN789',
            'payment_date' => now()->toDateString(),
            'payment_confirmation' => '1',
        ];
    }

    // ─── Service Tests ───────────────────────────────────────────────

    public function test_service_stores_payment_proof_and_details(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $file = $this->fakeProofFile();
        $data = $this->validPaymentData();

        $result = $this->service->submitPayment($transaction, $data, $file);

        $this->assertEquals('pending', $result->status);
        $this->assertEquals('eft', $result->payment_method);
        $this->assertEquals('fnb_namibia', $result->payment_method_detail);
        $this->assertEquals('QS-LOAN-1-1', $result->payment_reference);
        $this->assertNotNull($result->payment_proof_path);
        $this->assertStringStartsWith('funding-payments/', $result->payment_proof_path);

        Storage::disk('public')->assertExists($result->payment_proof_path);
    }

    public function test_service_stores_reference_and_transaction_in_metadata(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $data = $this->validPaymentData();
        $result = $this->service->submitPayment($transaction, $data, $this->fakeProofFile());

        $this->assertEquals('REF123456', $result->metadata['payer_reference_number']);
        $this->assertEquals('TXN789', $result->metadata['payer_transaction_number']);
    }

    public function test_service_throws_for_non_pending_transaction(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);
        $transaction->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        $this->expectException(\App\Exceptions\ApiException::class);
        $this->expectExceptionMessage('This funding transaction cannot be updated.');

        $this->service->submitPayment($transaction, $this->validPaymentData(), $this->fakeProofFile());
    }

    public function test_service_does_not_create_investment_on_submission(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $this->service->submitPayment($transaction, $this->validPaymentData(), $this->fakeProofFile());

        $this->assertEquals(0, Investment::where('loan_id', $loan->id)->count());
    }

    public function test_service_does_not_change_loan_status_on_submission(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $this->service->submitPayment($transaction, $this->validPaymentData(), $this->fakeProofFile());

        $loan->refresh();
        $this->assertEquals('marketplace', $loan->status);
        $this->assertEquals(0, (float) $loan->funded_amount);
    }

    // ─── Web Controller Tests ────────────────────────────────────────

    public function test_lender_can_submit_payment_proof_via_web(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $response = $this->actingAs($this->lender)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'eft',
                'payment_method_detail' => 'fnb_namibia',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'REF123456',
                'transaction_number' => 'TXN789',
                'payment_date' => now()->toDateString(),
                'proof_of_payment' => File::create('proof.pdf', 1024, 'application/pdf'),
                'payment_confirmation' => '1',
            ]);

        $response->assertRedirect(route('client.investments.index'));
        $response->assertSessionHas('success');

        $transaction->refresh();
        $this->assertEquals('pending', $transaction->status);
        $this->assertEquals('eft', $transaction->payment_method);
        $this->assertEquals('fnb_namibia', $transaction->payment_method_detail);
        $this->assertNotNull($transaction->payment_proof_path);
        $this->assertNotNull($transaction->payment_date);
    }

    public function test_cannot_submit_payment_for_confirmed_transaction(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);
        $transaction->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        $response = $this->actingAs($this->lender)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'eft',
                'payment_method_detail' => 'fnb_namibia',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'REF123',
                'payment_date' => now()->toDateString(),
                'proof_of_payment' => File::create('proof.pdf', 1024, 'application/pdf'),
                'payment_confirmation' => '1',
            ]);

        $response->assertRedirect(route('client.funding.show', $transaction));
        $response->assertSessionHas('error');
    }

    public function test_payment_proof_file_is_required(): void
    {
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $response = $this->actingAs($this->lender)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'eft',
                'payment_method_detail' => 'fnb_namibia',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'REF123',
                'payment_date' => now()->toDateString(),
                'payment_confirmation' => '1',
            ]);

        $response->assertSessionHasErrors('proof_of_payment');
    }

    public function test_other_lender_cannot_submit_payment(): void
    {
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $lender2 = User::factory()->active()->create();
        $this->assignClientRole($lender2);
        KycSubmission::factory()->approved()->create(['user_id' => $lender2->id]);

        $response = $this->actingAs($lender2)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'eft',
                'payment_method_detail' => 'fnb_namibia',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'REF123',
                'payment_date' => now()->toDateString(),
                'proof_of_payment' => File::create('proof.pdf', 1024, 'application/pdf'),
                'payment_confirmation' => '1',
            ]);

        $response->assertStatus(403);
    }

    public function test_no_investment_created_after_payment_submission(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $this->actingAs($this->lender)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'eft',
                'payment_method_detail' => 'fnb_namibia',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'REF123',
                'payment_date' => now()->toDateString(),
                'proof_of_payment' => File::create('proof.pdf', 1024, 'application/pdf'),
                'payment_confirmation' => '1',
            ]);

        $this->assertEquals(0, Investment::where('loan_id', $loan->id)->count());
    }

    public function test_mobile_wallet_payment_submission(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $response = $this->actingAs($this->lender)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'mobile_wallet',
                'payment_method_detail' => 'fnb_ewallet',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'WALLET-REF-001',
                'payment_date' => now()->toDateString(),
                'proof_of_payment' => File::create('proof.jpg', 1024, 'image/jpeg'),
                'payment_confirmation' => '1',
            ]);

        $response->assertRedirect(route('client.investments.index'));

        $transaction->refresh();
        $this->assertEquals('mobile_wallet', $transaction->payment_method);
        $this->assertEquals('fnb_ewallet', $transaction->payment_method_detail);
    }

    public function test_cash_deposit_payment_submission(): void
    {
        Storage::fake('public');
        Queue::fake();

        $loan = $this->createMarketplaceLoan();
        $transaction = $this->createPendingFundingTransaction($loan);

        $response = $this->actingAs($this->lender)
            ->post(route('client.funding.payment.submit', $transaction), [
                'payment_method' => 'cash_deposit',
                'payment_method_detail' => 'fnb_namibia',
                'payment_reference' => $transaction->payment_reference,
                'reference_number' => 'CASH-DEP-001',
                'payment_date' => now()->toDateString(),
                'proof_of_payment' => File::create('proof.png', 1024, 'image/png'),
                'payment_confirmation' => '1',
            ]);

        $response->assertRedirect(route('client.investments.index'));

        $transaction->refresh();
        $this->assertEquals('cash_deposit', $transaction->payment_method);
    }
}
