<?php

namespace Database\Factories;

use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Models\ReconciliationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReconciliationLogFactory extends Factory
{
    protected $model = ReconciliationLog::class;

    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'external_loan_id' => $this->faker->optional()->numerify('EXT-#####'),
            'provider' => 'mifos',
            'operation' => $this->faker->randomElement(['create', 'update', 'status_sync', 'reconcile']),
            'direction' => $this->faker->randomElement(['outbound', 'inbound']),
            'status' => $this->faker->randomElement(['pending', 'success', 'failed', 'skipped']),
            'request_payload' => $this->faker->optional()->passthrough(['test' => 'data']),
            'response_payload' => $this->faker->optional()->passthrough(['result' => 'ok']),
            'error_message' => $this->faker->optional()->sentence(),
            'http_status' => $this->faker->optional()->randomElement([200, 201, 400, 500]),
            'started_at' => now(),
            'completed_at' => $this->faker->optional()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function success(): static
    {
        return $this->state([
            'status' => 'success',
            'http_status' => 200,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'http_status' => 500,
            'error_message' => 'Connection error',
            'completed_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'completed_at' => null,
        ]);
    }
}
