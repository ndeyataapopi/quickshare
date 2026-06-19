<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycSubmissionFactory extends Factory
{
    protected $model = KycSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => 'rejected',
            'rejection_reason' => 'Documents unclear',
            'reviewed_at' => now(),
        ]);
    }
}
