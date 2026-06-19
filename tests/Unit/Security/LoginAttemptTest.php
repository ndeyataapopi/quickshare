<?php

namespace Tests\Unit\Security;

use App\Models\LoginAttempt;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_attempt_has_user_relationship(): void
    {
        $user = User::factory()->create();
        LoginAttempt::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $attempt = LoginAttempt::first();

        $this->assertInstanceOf(User::class, $attempt->user);
    }

    public function test_successful_scope(): void
    {
        LoginAttempt::factory()->create(['success' => false]);
        LoginAttempt::factory()->create(['success' => true]);

        $this->assertCount(1, LoginAttempt::successful()->get());
    }

    public function test_failed_scope(): void
    {
        LoginAttempt::factory()->create(['success' => false]);
        LoginAttempt::factory()->create(['success' => true]);

        $this->assertCount(1, LoginAttempt::failed()->get());
    }

    public function test_for_email_scope(): void
    {
        LoginAttempt::factory()->create(['email' => 'test@example.com']);
        LoginAttempt::factory()->create(['email' => 'other@example.com']);

        $this->assertCount(1, LoginAttempt::forEmail('test@example.com')->get());
    }

    public function test_for_ip_scope(): void
    {
        LoginAttempt::factory()->create(['ip_address' => '127.0.0.1']);
        LoginAttempt::factory()->create(['ip_address' => '192.168.1.1']);

        $this->assertCount(1, LoginAttempt::forIp('127.0.0.1')->get());
    }

    public function test_recent_scope(): void
    {
        LoginAttempt::factory()->create(['created_at' => now()->subMinutes(10)]);
        LoginAttempt::factory()->create(['created_at' => now()->subMinutes(5)]);

        $this->assertCount(1, LoginAttempt::recent(8)->get());
    }
}
