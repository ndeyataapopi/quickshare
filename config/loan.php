<?php

$durations = static fn (string $value): array => array_values(array_unique(array_map(
    static fn (string $duration): int => (int) trim($duration),
    explode(',', $value),
)));

return [
    'minimum_borrow_score' => env('LOAN_MIN_BORROW_SCORE', 30.00),

    'agreement' => [
        'disk' => env('LOAN_AGREEMENT_DISK', 'local'),
        'version' => env('LOAN_AGREEMENT_VERSION', '1.0'),
        'terms' => 'The borrower agrees to repay the total repayment amount by the repayment date shown in this agreement.',
        'conditions' => 'This agreement is subject to successful loan approval, funding, identity verification, and all applicable platform policies and laws.',
    ],

    'trust_tiers' => [
        'bronze' => [
            'trust_score' => [
                'min' => env('LOAN_TIER_BRONZE_MIN', 0.00),
                'max' => env('LOAN_TIER_BRONZE_MAX', 49.99),
            ],
            'name' => env('LOAN_TIER_BRONZE_NAME', 'bronze'),
            'maximum_loan' => env('LOAN_LIMIT_BRONZE', 500.00),
            'interest_percent' => env('LOAN_INTEREST_BRONZE', 30.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_BRONZE', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_BRONZE', 30.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_BRONZE', implode(',', range(7, 30)))),
        ],
        'silver' => [
            'trust_score' => [
                'min' => env('LOAN_TIER_SILVER_MIN', 50.00),
                'max' => env('LOAN_TIER_SILVER_MAX', 69.99),
            ],
            'name' => env('LOAN_TIER_SILVER_NAME', 'silver'),
            'maximum_loan' => env('LOAN_LIMIT_SILVER', 1000.00),
            'interest_percent' => env('LOAN_INTEREST_SILVER', 30.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_SILVER', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_SILVER', 30.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_SILVER', implode(',', range(7, 30)))),
        ],
        'gold' => [
            'trust_score' => [
                'min' => env('LOAN_TIER_GOLD_MIN', 70.00),
                'max' => env('LOAN_TIER_GOLD_MAX', 84.99),
            ],
            'name' => env('LOAN_TIER_GOLD_NAME', 'gold'),
            'maximum_loan' => env('LOAN_LIMIT_GOLD', 1500.00),
            'interest_percent' => env('LOAN_INTEREST_GOLD', 30.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_GOLD', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_GOLD', 30.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_GOLD', implode(',', range(7, 30)))),
        ],
        'platinum' => [
            'trust_score' => [
                'min' => env('LOAN_TIER_PLATINUM_MIN', 85.00),
                'max' => env('LOAN_TIER_PLATINUM_MAX', 100.00),
            ],
            'name' => env('LOAN_TIER_PLATINUM_NAME', 'platinum'),
            'maximum_loan' => env('LOAN_LIMIT_PLATINUM', 1500.00),
            'interest_percent' => env('LOAN_INTEREST_PLATINUM', 30.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_PLATINUM', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_PLATINUM', 30.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_PLATINUM', implode(',', range(7, 30)))),
        ],
    ],
];
