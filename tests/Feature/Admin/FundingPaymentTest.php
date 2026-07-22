<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundingPaymentTest extends TestCase
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

    public function test_admin_can_confirm_funding_payment(): void
    {
        $borrower = User::factory()->active()->create(['trust_score' => 50]);
        $this->assignClientRole($borrower);

        $lender = User::factory()->active()->create(['trust_score' => 70]);

        $loan = Loan::create([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 1000.00,
            'approved_amount' => 1000.00,
            'interest_rate' => 30.00,
            'platform_fee' => 50.00,
            'total_repayment' => 1300.00,
            'funded_amount' => 0.00,
            'loan_term_days' => 30,
            'risk_score' => 50.00,
            'status' => 'marketplace',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $transaction = FundingTransaction::create([
            'loan_id' => $loan->id,
            'lender_id' => $lender->id,
            'amount' => 500.00,
            'interest_rate' => 25.00,
            'expected_return' => 625.00,
            'status' => 'pending',
            'transaction_reference' => FundingTransaction::generateReference(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.funding-payments.confirm', $transaction));

        $response->assertRedirect(route('admin.funding-payments.show', $transaction));

        $this->assertEquals('confirmed', $transaction->fresh()->status);
        $this->assertEquals(500.00, $loan->fresh()->funded_amount);
    }
}
