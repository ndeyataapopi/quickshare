<?php

namespace Tests\Unit\Loans;

use App\Modules\Loans\Adapters\MifosAdapter;
use App\Modules\Loans\Jobs\SyncLoanToExternalJob;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Models\ReconciliationLog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncLoanToExternalJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_job_skips_loans_in_early_states(): void
    {
        Config::set('mifos.enabled', true);

        $loan = Loan::factory()->create(['status' => 'draft']);

        Queue::fake();
        $job = new SyncLoanToExternalJob($loan->id, 'create');
        $job->handle(app(MifosAdapter::class));

        // Should not create a reconciliation log for draft loans
        $this->assertEquals(0, ReconciliationLog::count());
    }

    public function test_job_creates_reconciliation_log(): void
    {
        Config::set('mifos.enabled', false);

        $loan = Loan::factory()->create(['status' => 'marketplace']);

        $job = new SyncLoanToExternalJob($loan->id, 'create');
        $job->handle(app(MifosAdapter::class));

        $log = ReconciliationLog::where('loan_id', $loan->id)->first();

        $this->assertNotNull($log);
        $this->assertEquals('create', $log->operation);
        $this->assertEquals('outbound', $log->direction);
        $this->assertEquals('mifos', $log->provider);
    }

    public function test_job_stores_external_id_on_successful_create(): void
    {
        Config::set('mifos.enabled', false);

        // Mock successful response
        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('createLoan')->willReturn([
            'success' => true,
            'data' => ['loanId' => 'EXT-99999'],
        ]);

        $loan = Loan::factory()->create(['status' => 'marketplace']);

        $job = new SyncLoanToExternalJob($loan->id, 'create');
        $job->handle($adapter);

        $loan->refresh();
        $this->assertEquals('EXT-99999', $loan->external_loan_id);
        $this->assertEquals('synced', $loan->sync_status);
        $this->assertNotNull($loan->last_synced_at);
    }

    public function test_job_updates_sync_status_on_success(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('updateLoan')->willReturn([
            'success' => true,
        ]);

        $loan = Loan::factory()->create([
            'status' => 'funded',
            'external_loan_id' => 'EXT-12345',
        ]);

        $job = new SyncLoanToExternalJob($loan->id, 'update');
        $job->handle($adapter);

        $loan->refresh();
        $this->assertEquals('synced', $loan->sync_status);
    }

    public function test_job_handles_different_operations(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('approveLoan')->willReturn(['success' => true]);

        $loan = Loan::factory()->create([
            'status' => 'funded',
            'external_loan_id' => 'EXT-12345',
        ]);

        $job = new SyncLoanToExternalJob($loan->id, 'approve');
        $job->handle($adapter);

        $log = ReconciliationLog::where('loan_id', $loan->id)->first();
        $this->assertEquals('approve', $log->operation);
    }

    public function test_job_marks_log_as_failed_on_error(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('createLoan')->willReturn([
            'success' => false,
            'error' => 'API error',
        ]);

        $loan = Loan::factory()->create(['status' => 'marketplace']);

        $job = new SyncLoanToExternalJob($loan->id, 'create');
        $job->handle($adapter);

        $log = ReconciliationLog::where('loan_id', $loan->id)->first();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals('API error', $log->error_message);
    }

    public function test_job_returns_early_if_loan_not_found(): void
    {
        Config::set('mifos.enabled', false);

        $job = new SyncLoanToExternalJob(99999, 'create');
        $job->handle(app(MifosAdapter::class));

        // Should not create any log
        $this->assertEquals(0, ReconciliationLog::count());
    }
}
