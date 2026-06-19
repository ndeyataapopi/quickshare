<?php

namespace Tests\Unit\Security;

use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_user_has_sessions_relationship(): void
    {
        $user = User::factory()->create();
        UserSession::factory()->create(['user_id' => $user->id]);

        $this->assertCount(1, $user->sessions);
    }

    public function test_current_session_scope(): void
    {
        $user = User::factory()->create();

        UserSession::factory()->create([
            'user_id' => $user->id,
            'is_current' => false,
        ]);

        UserSession::factory()->create([
            'user_id' => $user->id,
            'is_current' => true,
        ]);

        $this->assertCount(1, UserSession::current()->get());
    }

    public function test_expired_scope(): void
    {
        UserSession::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        UserSession::factory()->create([
            'expires_at' => now()->addDay(),
        ]);

        $this->assertCount(1, UserSession::expired()->get());
    }

    public function test_is_expired_helper(): void
    {
        $expiredSession = UserSession::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $activeSession = UserSession::factory()->create([
            'expires_at' => now()->addDay(),
        ]);

        $this->assertTrue($expiredSession->isExpired());
        $this->assertFalse($activeSession->isExpired());
    }
}
