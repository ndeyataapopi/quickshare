<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $requested = $this->faker->randomElement([2000, 3000, 5000, 7500, 10000]);
        $rate = $this->faker->randomFloat(2, 10, 25);
        $days = $this->faker->randomElement([30, 60, 90]);
        $interest = round($requested * ($rate / 100) * ($days / 365), 2);
        $fee = round($requested * 0.02, 2);

        return [
            'borrower_id' => User::factory(),
            'reference' => 'QS-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'requested_amount' => $requested,
            'approved_amount' => $requested,
            'interest_rate' => $rate,
            'platform_fee' => $fee,
            'total_repayment' => $requested + $interest + $fee,
            'funded_amount' => 0,
            'loan_term_days' => $days,
            'repayment_date' => now()->addDays($days)->toDateString(),
            'status' => 'pending_review',
            'risk_score' => $this->faker->randomFloat(2, 30, 90),
            'submitted_at' => now(),
            'external_loan_id' => null,
            'external_provider' => null,
            'sync_status' => null,
            'last_synced_at' => null,
            'external_metadata' => null,
        ];
    }

    public function marketplace(): static
    {
        return $this->state([
            'status' => 'marketplace',
            'approved_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'approved_at' => now()->subDays(5),
            'disbursed_at' => now()->subDays(3),
            'funded_amount' => fn (array $attrs) => $attrs['approved_amount'],
        ]);
    }
}
