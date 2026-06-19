<?php

namespace Database\Factories;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginAttemptFactory extends Factory
{
    protected $model = LoginAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => $this->faker->email(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'success' => $this->faker->boolean(70), // 70% success rate
            'failure_reason' => $this->faker->randomElement(['invalid_credentials', 'account_locked', null]),
            'location_country' => $this->faker->country(),
            'location_city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(-90, 90),
            'longitude' => $this->faker->longitude(-180, 180),
            'created_at' => now(),
        ];
    }
}
