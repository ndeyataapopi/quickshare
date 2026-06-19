<?php

namespace Tests\Feature\Auth;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_validate_referral_code_returns_valid_for_active_code(): void
    {
        $user = User::factory()->create();
        ReferralCode::create([
            'user_id' => $user->id,
            'code' => $user->referral_code,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/register/validate-referral', [
            'referral_code' => $user->referral_code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.referrer', $user->first_name);
    }

    public function test_validate_referral_code_returns_error_for_invalid_code(): void
    {
        $response = $this->postJson('/api/auth/register/validate-referral', [
            'referral_code' => 'INVALID1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_view_their_referral_code(): void
    {
        $user = User::factory()->create();
        ReferralCode::create([
            'user_id' => $user->id,
            'code' => $user->referral_code,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/auth/referral/my-code');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.referral_code', $user->referral_code);
    }

    public function test_authenticated_user_can_view_their_referrals(): void
    {
        $referrer = User::factory()->create();
        ReferralCode::create([
            'user_id' => $referrer->id,
            'code' => $referrer->referral_code,
            'is_active' => true,
        ]);

        $referred = User::factory()->create(['referred_by' => $referrer->id]);
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'referral_code' => $referrer->referral_code,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($referrer)->getJson('/api/auth/referral/my-referrals');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_referrals', 1)
            ->assertJsonPath('data.completed_referrals', 1);
    }

    public function test_referral_completes_when_user_fully_verifies(): void
    {
        $referrer = User::factory()->create();
        $referralCodeModel = ReferralCode::create([
            'user_id' => $referrer->id,
            'code' => $referrer->referral_code,
            'is_active' => true,
        ]);

        $referred = User::factory()->pending()->create([
            'referred_by' => $referrer->id,
        ]);
        Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'referral_code' => $referrer->referral_code,
            'status' => 'pending',
        ]);

        // Simulate full verification
        $referred->update([
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'status' => 'active',
        ]);

        $referralService = app(\App\Modules\Auth\Services\ReferralService::class);
        $referralService->completeReferral($referred);

        $this->assertDatabaseHas('referrals', [
            'referred_id' => $referred->id,
            'status' => 'completed',
        ]);

        // Referrer trust score should increase
        $referrer->refresh();
        $this->assertEquals(52.00, (float) $referrer->trust_score);
    }

    public function test_unauthenticated_user_cannot_access_referral_endpoints(): void
    {
        $this->getJson('/api/auth/referral/my-code')->assertStatus(401);
        $this->getJson('/api/auth/referral/my-referrals')->assertStatus(401);
    }
}
