<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        RateLimiter::clear('auth');
    }

    // ─── Login ────────────────────────────────────────────────────────

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->active()->create(['password' => bcrypt('password123')]);
        $this->assignClientRole($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'email', 'first_name', 'trust_score'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson(['success' => true, 'data' => ['token_type' => 'Bearer']]);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->active()->create(['password' => bcrypt('correct')]);
        $this->assignClientRole($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_login_fails_for_suspended_user(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'password' => bcrypt('password123'),
        ]);
        $this->assignClientRole($user);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_returns_json_validation_error_for_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['email', 'password']]);
    }

    // ─── Me ───────────────────────────────────────────────────────────

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure([
                'data' => [
                    'id', 'email', 'first_name', 'last_name',
                    'trust_score' => ['score', 'tier', 'risk_level'],
                    'verification' => ['email_verified', 'phone_verified'],
                    'roles',
                ],
            ]);
    }

    public function test_unauthenticated_cannot_access_me(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    // ─── Update Profile ───────────────────────────────────────────────

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/auth/me', [
                'first_name' => 'Updated',
                'last_name' => 'Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.last_name', 'Name');
    }

    // ─── Logout ───────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Token should be deleted
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_logout_from_all_devices(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);
        $user->createToken('device1');
        $user->createToken('device2');
        $token3 = $user->createToken('device3')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $this->withToken($token3)
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ─── Tokens ───────────────────────────────────────────────────────

    public function test_user_can_list_active_tokens(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);
        $user->createToken('mobile');
        $user->createToken('web');
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/auth/tokens');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_revoke_specific_token(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $tokenToRevoke = $user->createToken('to-revoke');
        $activeToken = $user->createToken('active')->plainTextToken;

        $this->withToken($activeToken)
            ->deleteJson("/api/v1/auth/tokens/{$tokenToRevoke->accessToken->id}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenToRevoke->accessToken->id,
        ]);
    }

    // ─── Change Password ─────────────────────────────────────────────

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->active()->create(['password' => bcrypt('oldpassword')]);
        $this->assignClientRole($user);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'oldpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk();
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->active()->create(['password' => bcrypt('correct')]);
        $this->assignClientRole($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrong',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertStatus(422);
    }

    // ─── Response Format ─────────────────────────────────────────────

    public function test_all_responses_are_json(): void
    {
        $user = User::factory()->active()->create();
        $this->assignClientRole($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_unversioned_auth_route_me_returns_404(): void
    {
        // Legacy module routes don't have /me endpoint
        $this->getJson('/api/auth/me')
            ->assertStatus(404);
    }
}
