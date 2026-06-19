<?php

namespace Tests\Unit\Security;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use App\Services\SecurityService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_log_login_attempt_creates_record(): void
    {
        $service = new SecurityService();

        $service->logLoginAttempt([
            'email' => 'test@example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'success' => true,
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'test@example.com',
            'ip_address' => '127.0.0.1',
            'success' => true,
        ]);
    }

    public function test_login_rate_limit_blocks_after_5_attempts(): void
    {
        $service = new SecurityService();

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($service->isLoginRateLimited('test@example.com', '127.0.0.1'));
        }

        // 6th attempt should be rate limited
        $this->assertTrue($service->isLoginRateLimited('test@example.com', '127.0.0.1'));
    }

    public function test_detects_unusual_location(): void
    {
        $user = User::factory()->create();

        UserSession::factory()->create([
            'user_id' => $user->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060, // New York
        ]);

        $request = Request::create('/', 'GET');
        $request->merge([
            'latitude' => 51.5074,
            'longitude' => -0.1278, // London (>500km away)
        ]);

        $service = new SecurityService();
        $suspicious = $service->detectSuspiciousActivity($user, $request);

        $this->assertContains('unusual_location', $suspicious);
    }

    public function test_detects_multiple_ip_attempts(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        LoginAttempt::factory()->count(5)->create([
            'email' => 'test@example.com',
            'success' => false,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subMinutes(10),
        ]);

        LoginAttempt::factory()->create([
            'email' => 'test@example.com',
            'success' => false,
            'ip_address' => '192.168.1.1',
            'created_at' => now()->subMinutes(5),
        ]);

        LoginAttempt::factory()->create([
            'email' => 'test@example.com',
            'success' => false,
            'ip_address' => '10.0.0.1',
            'created_at' => now()->subMinutes(2),
        ]);

        $request = Request::create('/');
        $service = new SecurityService();
        $suspicious = $service->detectSuspiciousActivity($user, $request);

        $this->assertContains('multiple_ips', $suspicious);
    }

    public function test_creates_user_session(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $service = new SecurityService();
        $session = $service->createUserSession($user, $request);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'is_current' => true,
        ]);
    }

    public function test_detects_device_type(): void
    {
        $service = new SecurityService();

        $request = Request::create('/');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)');

        $this->assertEquals('mobile', $service->detectDeviceType($request));
    }

    public function test_cleanup_expired_sessions(): void
    {
        UserSession::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        UserSession::factory()->create([
            'expires_at' => now()->addDay(),
        ]);

        $service = new SecurityService();
        $deleted = $service->cleanupExpiredSessions();

        $this->assertEquals(1, $deleted);
    }
}
