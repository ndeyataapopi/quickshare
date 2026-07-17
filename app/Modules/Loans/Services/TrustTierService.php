<?php

namespace App\Modules\Loans\Services;

use InvalidArgumentException;
use RuntimeException;

class TrustTierService
{
    public function forScore(float $score): array
    {
        foreach ($this->tiers() as $key => $tier) {
            $minimum = (float) ($tier['trust_score']['min'] ?? 0);
            $maximum = (float) ($tier['trust_score']['max'] ?? 0);

            if ($score >= $minimum && $score <= $maximum) {
                return $this->normalize($key, $tier);
            }
        }

        throw new InvalidArgumentException("No trust tier is configured for score {$score}.");
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

        return [
            'key' => $key,
            'name' => (string) ($tier['name'] ?? $key),
            'trust_score' => [
                'min' => (float) ($tier['trust_score']['min'] ?? 0),
                'max' => (float) ($tier['trust_score']['max'] ?? 0),
            ],
            'maximum_loan' => (float) ($tier['maximum_loan'] ?? 0),
            'interest_percent' => (float) ($tier['interest_percent'] ?? 0),
            'platform_fee_percent' => (float) ($tier['platform_fee_percent'] ?? 0),
            'lender_return_percent' => (float) ($tier['lender_return_percent'] ?? 0),
            'allowed_durations' => $durations,
        ];
    }
}
