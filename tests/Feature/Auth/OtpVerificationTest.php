<?php

namespace Tests\Feature\Auth;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_otp_can_be_sent_to_phone_number(): void
    {
        $response = $this->postJson('/api/auth/register/send-otp', [
            'phone' => '+27821234567',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('phone_verifications', [
            'phone' => '+27821234567',
        ]);
    }

    public function test_otp_can_be_verified(): void
    {
        $verification = PhoneVerification::create([
            'phone' => '+27821234567',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/register/verify-otp', [
            'phone' => '+27821234567',
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone_verified', true);

        $this->assertNotNull($verification->fresh()->verified_at);
    }

    public function test_expired_otp_cannot_be_verified(): void
    {
        PhoneVerification::create([
            'phone' => '+27821234567',
            'otp' => '123456',
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/auth/register/verify-otp', [
            'phone' => '+27821234567',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_wrong_otp_fails_verification(): void
    {
        PhoneVerification::create([
            'phone' => '+27821234567',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/register/verify-otp', [
            'phone' => '+27821234567',
            'otp' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_max_attempts_exceeded_blocks_verification(): void
    {
        PhoneVerification::create([
            'phone' => '+27821234567',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 5,
        ]);

        $response = $this->postJson('/api/auth/register/verify-otp', [
            'phone' => '+27821234567',
            'otp' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cooldown_prevents_rapid_otp_requests(): void
    {
        PhoneVerification::create([
            'phone' => '+27821234567',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register/send-otp', [
            'phone' => '+27821234567',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_phone_marked_verified_after_otp(): void
    {
        $user = User::factory()->pending()->create([
            'phone' => '+27821234567',
            'phone_verified_at' => null,
        ]);

        PhoneVerification::create([
            'phone' => '+27821234567',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/verify-phone', [
            'phone' => '+27821234567',
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone_verified', true)
            ->assertJsonStructure(['data' => ['phone_verified', 'status']]);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }
}
