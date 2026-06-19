<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class FundingTransactionFactory extends Factory
{
    protected $model = FundingTransaction::class;

    public function definition(): array
    {
        $amount = $this->faker->randomElement([500, 1000, 2500, 5000]);
        $rate = $this->faker->randomFloat(2, 10, 20);
        $days = 60;
        $expectedReturn = round($amount * (1 + ($rate / 100) * ($days / 365)), 2);

        return [
            'loan_id' => Loan::factory(),
            'lender_id' => User::factory(),
            'amount' => $amount,
            'interest_rate' => $rate,
            'expected_return' => $expectedReturn,
            'status' => 'pending',
            'transaction_reference' => 'FUND-' . strtoupper($this->faker->unique()->lexify('????????????')),
        ];
    }

    public function confirmed(): static
    {
        return $this->state([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }
}
