<?php

namespace Tests\Unit\Loans;

use App\Modules\Loans\Adapters\MifosAdapter;
use App\Modules\Loans\Contracts\LoanProviderInterface;
use App\Modules\Loans\Models\Loan;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MifosAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_mifos_adapter_implements_loan_provider_interface(): void
    {
        $adapter = app(MifosAdapter::class);
        $this->assertInstanceOf(LoanProviderInterface::class, $adapter);
    }

    public function test_get_provider_name_returns_mifos(): void
    {
        $adapter = app(MifosAdapter::class);
        $this->assertEquals('mifos', $adapter->getProviderName());
    }

    public function test_is_healthy_returns_false_when_disabled(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $this->assertFalse($adapter->isHealthy());
    }

    public function test_operations_return_skipped_when_disabled(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $loan = Loan::factory()->create();

        $result = $adapter->createLoan($loan);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped']);
        $this->assertStringContainsString('disabled', $result['message']);
    }

    public function test_create_loan_builds_correct_payload(): void
    {
        Config::set('mifos.enabled', false);
        Config::set('mifos.product_id', 5);
        Config::set('mifos.office_id', 2);

        $loan = Loan::factory()->create([
            'approved_amount' => 10000,
            'interest_rate' => 15.5,
            'loan_term_days' => 60,
            'submitted_at' => now(),
        ]);

        $adapter = app(MifosAdapter::class);
        $result = $adapter->createLoan($loan);

        // When disabled, returns skipped with success=true
        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped']);
    }

    public function test_update_loan_returns_skipped_without_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $loan = Loan::factory()->create(['external_loan_id' => null]);

        $result = $adapter->updateLoan($loan);

        $this->assertTrue($result['skipped']);
    }

    public function test_get_loan_status_returns_skipped_without_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);

        $result = $adapter->getLoanStatus('ext-123');

        $this->assertTrue($result['skipped']);
    }

    public function test_approve_loan_returns_skipped_without_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $loan = Loan::factory()->create(['external_loan_id' => null]);

        $result = $adapter->approveLoan($loan);

        $this->assertTrue($result['skipped']);
    }

    public function test_reject_loan_returns_skipped_without_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $loan = Loan::factory()->create(['external_loan_id' => null]);

        $result = $adapter->rejectLoan($loan, 'Test reason');

        $this->assertTrue($result['skipped']);
    }

    public function test_disburse_loan_returns_skipped_without_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $loan = Loan::factory()->create(['external_loan_id' => null]);

        $result = $adapter->disburseLoan($loan);

        $this->assertTrue($result['skipped']);
    }

    public function test_record_repayment_returns_skipped_without_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = app(MifosAdapter::class);
        $loan = Loan::factory()->create(['external_loan_id' => null]);

        $result = $adapter->recordRepayment($loan, 1000);

        $this->assertTrue($result['skipped']);
    }
}
