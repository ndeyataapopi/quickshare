<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSessionFactory extends Factory
{
    protected $model = UserSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet', null]),
            'device_name' => $this->faker->randomElement(['iPhone', 'Android', 'Windows', 'macOS', null]),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge', null]),
            'platform' => $this->faker->randomElement(['Windows 10', 'macOS', 'Android', 'iOS', null]),
            'location_country' => $this->faker->country(),
            'location_city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(-90, 90),
            'longitude' => $this->faker->longitude(-180, 180),
            'is_current' => $this->faker->boolean(),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
