<?php

namespace App\Modules\Loans\Services;

use InvalidArgumentException;
use RuntimeException;

class TrustTierService
{
    public function forScore(float $score): array
    {
        $score = max(0.00, min(100.00, $score));

        foreach ($this->tiers() as $key => $tier) {
            $minimum = (float) ($tier['trust_score']['min'] ?? 0);
            $maximum = (float) ($tier['trust_score']['max'] ?? 0);

            if ($score >= $minimum && $score <= $maximum) {
                return $this->normalize($key, $tier);
            }
        }

        $tiers = $this->tiers();
        $firstKey = array_key_first($tiers);
        $lastKey = array_key_last($tiers);

        if ($score <= (float) ($tiers[$firstKey]['trust_score']['min'] ?? 0)) {
            return $this->normalize($firstKey, $tiers[$firstKey]);
        }

        return $this->normalize($lastKey, $tiers[$lastKey]);
    }

    public function forName(string $name): array
    {
        foreach ($this->tiers() as $key => $tier) {
            if ($key === $name || ($tier['name'] ?? null) === $name) {
                return $this->normalize($key, $tier);
            }
        }

        throw new InvalidArgumentException("Trust tier {$name} is not configured.");
    }

    public function minimumBorrowScore(): float
    {
        return (float) config('loan.minimum_borrow_score');
    }

    public function names(): array
    {
        return array_values(array_map(
            static fn (array $tier, string $key): string => (string) ($tier['name'] ?? $key),
            $this->tiers(),
            array_keys($this->tiers()),
        ));
    }

    protected function tiers(): array
    {
        $tiers = config('loan.trust_tiers');

        if (! is_array($tiers) || $tiers === []) {
            throw new RuntimeException('Loan trust tiers are not configured.');
        }

        return $tiers;
    }

    protected function normalize(string $key, array $tier): array
    {
        $durations = array_values(array_unique(array_map('intval', $tier['allowed_durations'] ?? [])));
        sort($durations);

        if ($durations === []) {
            throw new RuntimeException("Trust tier {$key} has no allowed durations.");
        }

        $platformFeePercent = (float) ($tier['platform_fee_percent'] ?? 0);
        $lenderReturnPercent = (float) ($tier['lender_return_percent'] ?? 0);
        $totalChargePercent = $platformFeePercent + $lenderReturnPercent;

        return [
            'key' => $key,
            'name' => (string) ($tier['name'] ?? $key),
            'trust_score' => [
                'min' => (float) ($tier['trust_score']['min'] ?? 0),
                'max' => (float) ($tier['trust_score']['max'] ?? 0),
            ],
            'minimum_loan' => (float) ($tier['minimum_loan'] ?? 0),
            'maximum_loan' => (float) ($tier['maximum_loan'] ?? 0),
            'platform_fee_percent' => $platformFeePercent,
            'lender_return_percent' => $lenderReturnPercent,
            'interest_percent' => $totalChargePercent,
            'total_charge_percent' => $totalChargePercent,
            'allowed_durations' => $durations,
            'eligibility_rules' => (array) ($tier['eligibility_rules'] ?? []),
        ];
    }
}
