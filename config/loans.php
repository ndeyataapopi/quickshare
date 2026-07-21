<?php

/**
 * @deprecated This file exists only for backward compatibility while the
 * application migrates to config('loan.*'). Use config/loan.php as the
 * single source of truth; this shim will be removed in a future refactor.
 */

$loan = require __DIR__.'/loan.php';

return [
    'currency' => $loan['general']['currency'] ?? null,
    'currency_symbol' => $loan['general']['currency_symbol'] ?? null,
    'interest_rate' => $loan['fees']['default_interest_rate'] ?? null,
    'platform_fee_percent' => $loan['fees']['default_platform_fee_percent'] ?? null,
    'min_term_days' => $loan['loan_limits']['min_term_days'] ?? null,
    'max_term_days' => $loan['loan_limits']['max_term_days'] ?? null,
    'min_amount' => $loan['loan_limits']['min_amount'] ?? null,
    'max_amount' => $loan['loan_limits']['max_amount'] ?? null,
    'max_active_loans' => $loan['loan_limits']['max_active_loans'] ?? null,
    'min_funding_percent' => $loan['marketplace']['min_funding_percent'] ?? null,
    'affordability' => $loan['affordability'] ?? null,
    'min_funding_amount' => $loan['marketplace']['min_funding_amount'] ?? null,
    'tier_limits' => collect($loan['trust_tiers'] ?? [])
        ->map(fn (array $tier) => $tier['maximum_loan'] ?? 0)
        ->all(),
    'trust_score' => [
        'min_borrow_score' => $loan['minimum_borrow_score'] ?? null,
        'tier_platinum_min' => $loan['trust_tiers']['platinum']['trust_score']['min'] ?? null,
        'tier_gold_min' => $loan['trust_tiers']['gold']['trust_score']['min'] ?? null,
        'tier_silver_min' => $loan['trust_tiers']['silver']['trust_score']['min'] ?? null,
    ],
];
