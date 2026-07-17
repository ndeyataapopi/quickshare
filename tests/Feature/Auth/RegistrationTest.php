<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\ReferralCode;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $referrer = User::factory()->active()->create();
        $referralCode = ReferralCode::create([
            'user_id' => $referrer->id,
            'code' => $referrer->referral_code,
            'is_active' => true,
        ]);

        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'national_id' => '99010112345',
            'phone' => '+27600000001',
            'date_of_birth' => '1999-01-01',
            'referral_code' => $referralCode->code,
            'password' => 'password',
            'password_confirmation' => 'password',
            'address' => [
                'country' => 'South Africa',
                'city' => 'Johannesburg',
                'street' => 'Main Road',
                'house_number' => '1',
            ],
            'source_of_income' => [
                'profession' => 'unemployed',
            ],
        ]);

        $this->assertAuthenticated();
        $this->assertTrue(User::where('email', 'test@example.com')->firstOrFail()->hasRole('client'));
        $response->assertRedirect(route('verification.notice', absolute: false));
    }
}
