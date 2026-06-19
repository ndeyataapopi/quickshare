<?php

namespace Tests\Unit\Loans;

use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Models\ReconciliationLog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_reconciliation_log_belongs_to_loan(): void
    {
        $loan = Loan::factory()->create();
        $log = ReconciliationLog::factory()->create(['loan_id' => $loan->id]);

        $this->assertInstanceOf(Loan::class, $log->loan);
        $this->assertEquals($loan->id, $log->loan->id);
    }

    public function test_is_success_returns_true_for_success_status(): void
    {
        $log = ReconciliationLog::factory()->create(['status' => 'success']);
        $this->assertTrue($log->isSuccess());
    }

    public function test_is_success_returns_false_for_non_success(): void
    {
        $log = ReconciliationLog::factory()->create(['status' => 'failed']);
        $this->assertFalse($log->isSuccess());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        $log = ReconciliationLog::factory()->create(['status' => 'failed']);
        $this->assertTrue($log->isFailed());
    }

    public function test_is_failed_returns_false_for_non_failed(): void
    {
        $log = ReconciliationLog::factory()->create(['status' => 'success']);
        $this->assertFalse($log->isFailed());
    }

    public function test_scope_for_loan_filters_by_loan_id(): void
    {
        $loan1 = Loan::factory()->create();
        $loan2 = Loan::factory()->create();

        ReconciliationLog::factory()->create(['loan_id' => $loan1->id]);
        ReconciliationLog::factory()->create(['loan_id' => $loan2->id]);
        ReconciliationLog::factory()->create(['loan_id' => $loan1->id]);

        $logs = ReconciliationLog::forLoan($loan1->id)->get();
        $this->assertCount(2, $logs);
        $this->assertTrue($logs->every(fn ($log) => $log->loan_id === $loan1->id));
    }

    public function test_scope_for_provider_filters_by_provider(): void
    {
        ReconciliationLog::factory()->create(['provider' => 'mifos']);
        ReconciliationLog::factory()->create(['provider' => 'mifos']);
        ReconciliationLog::factory()->create(['provider' => 'other']);

        $logs = ReconciliationLog::forProvider('mifos')->get();
        $this->assertCount(2, $logs);
    }

    public function test_scope_successful_filters_by_success_status(): void
    {
        ReconciliationLog::factory()->create(['status' => 'success']);
        ReconciliationLog::factory()->create(['status' => 'failed']);
        ReconciliationLog::factory()->create(['status' => 'pending']);
        ReconciliationLog::factory()->create(['status' => 'success']);

        $logs = ReconciliationLog::successful()->get();
        $this->assertCount(2, $logs);
    }

    public function test_scope_failed_filters_by_failed_status(): void
    {
        ReconciliationLog::factory()->create(['status' => 'success']);
        ReconciliationLog::factory()->create(['status' => 'failed']);
        ReconciliationLog::factory()->create(['status' => 'failed']);

        $logs = ReconciliationLog::failed()->get();
        $this->assertCount(2, $logs);
    }

    public function test_scope_by_operation_filters_by_operation(): void
    {
        ReconciliationLog::factory()->create(['operation' => 'create']);
        ReconciliationLog::factory()->create(['operation' => 'update']);
        ReconciliationLog::factory()->create(['operation' => 'create']);

        $logs = ReconciliationLog::byOperation('create')->get();
        $this->assertCount(2, $logs);
    }

    public function test_request_payload_is_cast_to_array(): void
    {
        $log = ReconciliationLog::factory()->create([
            'request_payload' => ['key' => 'value'],
        ]);

        $this->assertIsArray($log->request_payload);
        $this->assertEquals(['key' => 'value'], $log->request_payload);
    }

    public function test_response_payload_is_cast_to_array(): void
    {
        $log = ReconciliationLog::factory()->create([
            'response_payload' => ['result' => 'ok'],
        ]);

        $this->assertIsArray($log->response_payload);
        $this->assertEquals(['result' => 'ok'], $log->response_payload);
    }

    public function test_started_at_is_cast_to_datetime(): void
    {
        $log = ReconciliationLog::factory()->create([
            'started_at' => '2026-05-21 12:00:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $log->started_at);
    }

    public function test_completed_at_is_cast_to_datetime(): void
    {
        $log = ReconciliationLog::factory()->create([
            'completed_at' => '2026-05-21 12:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $log->completed_at);
    }
}
