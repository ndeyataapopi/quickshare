<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_can_confirm_disbursement(): void
    {
        $loan = $this->createFundedLoan();

        $service = app(DisbursementService::class);
        $service->initiateDisbursement($loan);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.disbursements.confirm', $loan));

        $response->assertRedirect(route('admin.disbursements.show', $loan))
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertNotNull($loan->disbursed_at);
        $this->assertDatabaseHas('disbursement_transactions', [
            'loan_id' => $loan->id,
            'status' => 'disbursed',
        ]);
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
}
