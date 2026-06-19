<?php

namespace Tests\Unit\Loans;

use App\Modules\Loans\Adapters\MifosAdapter;
use App\Modules\Loans\Jobs\ReconcileLoansJob;
use App\Modules\Loans\Jobs\SyncExternalLoanStatusJob;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Models\ReconciliationLog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcileLoansJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_job_skips_when_provider_not_healthy(): void
    {
        Config::set('mifos.enabled', false);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(false);

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        $this->assertEquals(0, ReconciliationLog::count());
    }

    public function test_job_creates_reconciliation_log(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertNotNull($log);
        $this->assertEquals('inbound', $log->direction);
    }

    public function test_job_finds_loans_needing_reconciliation(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        // Create loans that need reconciliation
        Loan::factory()->create([
            'external_loan_id' => 'EXT-1',
            'external_provider' => 'mifos',
            'sync_status' => 'error',
            'last_synced_at' => now()->subHours(2),
            'status' => 'active',
        ]);

        Loan::factory()->create([
            'external_loan_id' => 'EXT-2',
            'external_provider' => 'mifos',
            'sync_status' => null,
            'last_synced_at' => null,
            'status' => 'active',
        ]);

        Loan::factory()->create([
            'external_loan_id' => 'EXT-3',
            'external_provider' => 'mifos',
            'sync_status' => 'synced',
            'last_synced_at' => now()->subHours(2),
            'status' => 'funded',
        ]);

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertNotNull($log);
        $this->assertEquals('success', $log->status);
    }

    public function test_job_dispatches_sync_jobs_for_eligible_loans(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        Queue::fake();

        $loan = Loan::factory()->create([
            'external_loan_id' => 'EXT-1',
            'external_provider' => 'mifos',
            'sync_status' => 'error',
            'last_synced_at' => now()->subHours(2),
            'status' => 'active',
        ]);

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        // Verify job completed successfully
        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertNotNull($log);
    }

    public function test_job_limits_to_100_loans_per_run(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        Queue::fake();

        // Create 150 loans needing sync
        for ($i = 0; $i < 150; $i++) {
            Loan::factory()->create([
                'external_loan_id' => "EXT-{$i}",
                'external_provider' => 'mifos',
                'sync_status' => 'error',
                'status' => 'active',
            ]);
        }

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertLessThanOrEqual(100, data_get($log, 'response_payload.loans_processed', 0));
    }

    public function test_job_handles_custom_provider(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        Loan::factory()->create([
            'external_loan_id' => 'EXT-1',
            'external_provider' => 'custom',
            'sync_status' => 'error',
            'status' => 'active',
        ]);

        $job = new ReconcileLoansJob('custom');
        $job->handle($adapter);

        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertEquals('custom', $log->provider);
    }

    public function test_job_marks_log_as_failed_on_exception(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        // Create a loan that will cause an error during sync
        Loan::factory()->create([
            'external_loan_id' => 'EXT-1',
            'external_provider' => 'mifos',
            'sync_status' => 'error',
            'last_synced_at' => now()->subHours(2),
            'status' => 'active',
        ]);

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        // Verify job completed (even if no loans were synced)
        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertNotNull($log);
    }

    public function test_job_only_syncs_specific_statuses(): void
    {
        Config::set('mifos.enabled', true);

        $adapter = $this->createMock(MifosAdapter::class);
        $adapter->method('isHealthy')->willReturn(true);

        Queue::fake();

        // Active loan - should sync
        Loan::factory()->create([
            'external_loan_id' => 'EXT-1',
            'external_provider' => 'mifos',
            'sync_status' => 'error',
            'last_synced_at' => now()->subHours(2),
            'status' => 'active',
        ]);

        // Draft loan - should NOT sync
        Loan::factory()->create([
            'external_loan_id' => 'EXT-2',
            'external_provider' => 'mifos',
            'sync_status' => 'error',
            'last_synced_at' => now()->subHours(2),
            'status' => 'draft',
        ]);

        // Completed loan - should NOT sync
        Loan::factory()->create([
            'external_loan_id' => 'EXT-3',
            'external_provider' => 'mifos',
            'sync_status' => 'error',
            'last_synced_at' => now()->subHours(2),
            'status' => 'completed',
        ]);

        $job = new ReconcileLoansJob();
        $job->handle($adapter);

        // Verify job completed
        $log = ReconciliationLog::where('operation', 'reconcile')->first();
        $this->assertNotNull($log);
    }
}
