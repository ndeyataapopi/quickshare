<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepaymentFactory extends Factory
{
    protected $model = Repayment::class;

    public function definition(): array
    {
        $principal = $this->faker->randomElement([1000, 2000, 3000, 5000]);
        $interest = round($principal * 0.15 * (30 / 365), 2);
        $fee = round($principal * 0.02, 2);
        $total = $principal + $interest + $fee;

        return [
            'loan_id' => Loan::factory(),
            'borrower_id' => User::factory(),
            'amount' => $total,
            'principal' => $principal,
            'interest' => $interest,
            'penalty' => 0,
            'platform_fee' => $fee,
            'status' => 'pending',
            'due_date' => now()->addDays(30)->toDateString(),
            'transaction_reference' => 'REP-' . strtoupper($this->faker->unique()->lexify('????????????')),
            'payment_method' => 'bank_transfer',
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => 'paid',
            'paid_date' => now()->toDateString(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => 'overdue',
            'due_date' => now()->subDays(7)->toDateString(),
            'days_overdue' => 7,
            'penalty' => fn (array $attrs) => round($attrs['amount'] * 0.05, 2),
        ]);
    }
}
