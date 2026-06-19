<?php

namespace Tests\Unit\Loans;

use App\Modules\Loans\Adapters\MifosAdapter;
use App\Modules\Loans\Jobs\SyncExternalLoanStatusJob;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Models\ReconciliationLog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SyncExternalLoanStatusJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_job_returns_early_if_loan_not_found(): void
    {
        Config::set('mifos.enabled', false);

        $job = new SyncExternalLoanStatusJob(99999);
        $job->handle(app(MifosAdapter::class));

        $this->assertEquals(0, ReconciliationLog::count());
    }

    public function test_job_returns_early_if_loan_has_no_external_id(): void
    {
        Config::set('mifos.enabled', false);

        $loan = Loan::factory()->create(['external_loan_id' => null]);

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle(app(MifosAdapter::class));

        $this->assertEquals(0, ReconciliationLog::count());
    }

    public function test_job_creates_reconciliation_log(): void
    {
        Config::set('mifos.enabled', false);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle(app(MifosAdapter::class));

        $log = ReconciliationLog::where('loan_id', $loan->id)->first();

        $this->assertNotNull($log);
        $this->assertEquals('status_sync', $log->operation);
        $this->assertEquals('inbound', $log->direction);
    }

    public function test_job_updates_loan_status_on_successful_sync(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('getLoanStatus')->willReturn([
            'success' => true,
            'data' => ['status' => ['value' => 'in arrears']],
        ]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle($adapter);

        $loan->refresh();
        $this->assertEquals('active', $loan->status);
        $this->assertEquals('synced', $loan->sync_status);
    }

    public function test_job_maps_mifos_statuses_correctly(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('getLoanStatus')->willReturn([
            'success' => true,
            'data' => ['status' => ['value' => 'submitted and pending approval']],
        ]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'draft',
        ]);

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle($adapter);

        $loan->refresh();
        $this->assertEquals('pending_review', $loan->status);
    }

    public function test_job_does_not_update_status_if_same(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('getLoanStatus')->willReturn([
            'success' => true,
            'data' => ['status' => ['value' => 'active']],
        ]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $originalStatus = $loan->status;

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle($adapter);

        $loan->refresh();
        $this->assertEquals($originalStatus, $loan->status);
        $this->assertEquals('synced', $loan->sync_status);
    }

    public function test_job_handles_unknown_status_gracefully(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('getLoanStatus')->willReturn([
            'success' => true,
            'data' => ['status' => ['value' => 'unknown_status']],
        ]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $originalStatus = $loan->status;

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle($adapter);

        $loan->refresh();
        $this->assertEquals($originalStatus, $loan->status);
    }

    public function test_job_marks_log_as_failed_on_error(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('getLoanStatus')->willReturn([
            'success' => false,
            'error' => 'Connection failed',
        ]);

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-12345',
            'status' => 'active',
        ]);

        $job = new SyncExternalLoanStatusJob($loan->id);
        $job->handle($adapter);

        $log = ReconciliationLog::where('loan_id', $loan->id)->first();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals('Connection failed', $log->error_message);
    }
}
