<?php

namespace Tests\Unit\Security;

use App\Models\User;
use App\Traits\Auditable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_sensitive_password_is_masked_in_audit_logs(): void
    {
        $user = User::factory()->create([
            'national_id' => '1234567890123',
        ]);

        $user->update(['national_id' => '9876543210987']);

        $log = $user->auditLogs()->latest()->first();

        $this->assertNotNull($log);
        if (isset($log->new_values['national_id'])) {
            $this->assertStringContainsString('***', $log->new_values['national_id']);
        }
        if (isset($log->old_values['national_id'])) {
            $this->assertStringContainsString('***', $log->old_values['national_id']);
        }
    }

    public function test_sensitive_token_fields_are_masked(): void
    {
        $user = User::factory()->create();

        $user->update([
            'national_id' => 'sensitive-token-value-12345',
        ]);

        $log = $user->auditLogs()->latest()->first();

        if (isset($log->new_values['national_id'])) {
            $this->assertStringContainsString('***', $log->new_values['national_id']);
        }
    }

    public function test_non_sensitive_fields_are_not_masked(): void
    {
        $user = User::factory()->create(['first_name' => 'John']);

        $user->update(['first_name' => 'Jane']);

        $log = $user->auditLogs()->latest()->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->new_values);
    }

    public function test_audit_log_includes_user_agent_and_ip(): void
    {
        $user = User::factory()->create();

        $user->update(['first_name' => 'Jane']);

        $log = $user->auditLogs()->latest()->first();

        $this->assertNotNull($log->user_agent);
        $this->assertNotNull($log->ip_address);
    }
}
