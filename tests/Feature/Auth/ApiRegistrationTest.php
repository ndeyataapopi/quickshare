<?php

namespace Tests\Feature\Auth;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $referrer;
    protected ReferralCode $referralCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->referrer = User::factory()->active()->create();
        $this->referralCode = ReferralCode::create([
            'user_id' => $this->referrer->id,
            'code' => $this->referrer->referral_code,
            'is_active' => true,
        ]);
    }

    protected function validRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'referral_code' => $this->referralCode->code,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '9501015800081',
            'email' => 'john@example.com',
            'phone' => '+27821234567',
            'date_of_birth' => '1995-01-01',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'borrower',
            'address' => [
                'country' => 'South Africa',
                'city' => 'Johannesburg',
                'suburb' => 'Sandton',
                'street' => 'Main Road',
                'house_number' => '42',
            ],
            'source_of_income' => [
                'profession' => 'employed',
                'company_name' => 'Tech Corp',
                'city' => 'Johannesburg',
                'country' => 'South Africa',
            ],
        ], $overrides);
    }

    public function test_user_can_register_with_valid_referral_code(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'referral_code',
                        'trust_score',
                        'status',
                    ],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.user.first_name', 'John');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '9501015800081',
        ]);

        $this->assertDatabaseHas('addresses', [
            'country' => 'South Africa',
            'city' => 'Johannesburg',
        ]);

        $this->assertDatabaseHas('source_of_incomes', [
            'profession' => 'employed',
            'company_name' => 'Tech Corp',
        ]);
    }

    public function test_registration_fails_without_referral_code(): void
    {
        $data = $this->validRegistrationData();
        unset($data['referral_code']);

        $response = $this->postJson('/api/auth/register', $data);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_registration_fails_with_invalid_referral_code(): void
    {
        $data = $this->validRegistrationData(['referral_code' => 'INVALID1']);

        $response = $this->postJson('/api/auth/register', $data);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_registration_fails_with_duplicate_national_id(): void
    {
        User::factory()->create(['national_id' => '9501015800081']);

        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_registration_fails_with_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+27821234567']);

        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_registration_fails_for_underage_user(): void
    {
        $data = $this->validRegistrationData(['date_of_birth' => now()->subYears(16)->format('Y-m-d')]);

        $response = $this->postJson('/api/auth/register', $data);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_registration_creates_referral_record(): void
    {
        $this->postJson('/api/auth/register', $this->validRegistrationData());

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $this->referrer->id,
            'referral_code' => $this->referralCode->code,
            'status' => 'pending',
        ]);
    }

    public function test_registration_increments_referral_code_usage(): void
    {
        $this->postJson('/api/auth/register', $this->validRegistrationData());

        $this->assertDatabaseHas('referral_codes', [
            'id' => $this->referralCode->id,
            'usage_count' => 1,
        ]);
    }

    public function test_registration_generates_unique_referral_code_for_new_user(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(201);

        $newUser = User::where('email', 'john@example.com')->first();

        $this->assertNotNull($newUser->referral_code);
        $this->assertEquals(8, strlen($newUser->referral_code));

        $this->assertDatabaseHas('referral_codes', [
            'user_id' => $newUser->id,
            'code' => $newUser->referral_code,
            'is_active' => true,
        ]);
    }

    public function test_registration_assigns_borrower_role_by_default(): void
    {
        $data = $this->validRegistrationData();
        unset($data['role']);

        $response = $this->postJson('/api/auth/register', $data);
        $response->assertStatus(201);

        $newUser = User::where('email', 'john@example.com')->first();
        $this->assertTrue($newUser->hasRole('borrower'));
    }

    public function test_registration_can_assign_lender_role(): void
    {
        $data = $this->validRegistrationData(['role' => 'lender']);

        $response = $this->postJson('/api/auth/register', $data);
        $response->assertStatus(201);

        $newUser = User::where('email', 'john@example.com')->first();
        $this->assertTrue($newUser->hasRole('lender'));
    }

    public function test_inactive_referral_code_cannot_be_used(): void
    {
        $this->referralCode->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_exhausted_referral_code_cannot_be_used(): void
    {
        $this->referralCode->update(['max_uses' => 1, 'usage_count' => 1]);

        $response = $this->postJson('/api/auth/register', $this->validRegistrationData());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
