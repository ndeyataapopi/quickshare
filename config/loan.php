<?php

$durations = static fn (string $value): array => array_values(array_unique(array_map(
    static fn (string $duration): int => (int) trim($duration),
    explode(',', $value),
)));

return [
    'minimum_borrow_score' => env('LOAN_MIN_BORROW_SCORE', 30.00),

    'general' => [
        'currency' => env('LOAN_CURRENCY', 'NAD'),
        'currency_symbol' => env('LOAN_CURRENCY_SYMBOL', 'N$'),
    ],

    'fees' => [
        'default_interest_rate' => env('LOAN_INTEREST_RATE', 30.00),
        'default_platform_fee_percent' => env('LOAN_PLATFORM_FEE_PERCENT', 5.00),
    ],

    'loan_limits' => [
        'min_amount' => env('LOAN_MIN_AMOUNT', 500.00),
        'max_amount' => env('LOAN_MAX_AMOUNT', 1500.00),
        'min_term_days' => env('LOAN_MIN_TERM_DAYS', 7),
        'max_term_days' => env('LOAN_MAX_TERM_DAYS', 30),
        'max_active_loans' => env('LOAN_MAX_ACTIVE_LOANS', 5),
    ],

    'trust_tiers' => [
        'bronze' => [
            'name' => env('LOAN_TIER_BRONZE_NAME', 'bronze'),
            'trust_score' => [
                'min' => env('LOAN_TIER_BRONZE_MIN', 0.00),
                'max' => env('LOAN_TIER_BRONZE_MAX', 49.99),
            ],
            'minimum_loan' => env('LOAN_TIER_BRONZE_MIN_LOAN', 0.00),
            'maximum_loan' => env('LOAN_LIMIT_BRONZE', 500.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_BRONZE', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_BRONZE', 30.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_BRONZE', implode(',', range(7, 30)))),
            'eligibility_rules' => [],
        ],
        'silver' => [
            'name' => env('LOAN_TIER_SILVER_NAME', 'silver'),
            'trust_score' => [
                'min' => env('LOAN_TIER_SILVER_MIN', 50.00),
                'max' => env('LOAN_TIER_SILVER_MAX', 69.99),
            ],
            'minimum_loan' => env('LOAN_TIER_SILVER_MIN_LOAN', 0.00),
            'maximum_loan' => env('LOAN_LIMIT_SILVER', 1000.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_SILVER', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_SILVER', 29.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_SILVER', implode(',', range(7, 30)))),
            'eligibility_rules' => [],
        ],
        'gold' => [
            'name' => env('LOAN_TIER_GOLD_NAME', 'gold'),
            'trust_score' => [
                'min' => env('LOAN_TIER_GOLD_MIN', 70.00),
                'max' => env('LOAN_TIER_GOLD_MAX', 84.99),
            ],
            'minimum_loan' => env('LOAN_TIER_GOLD_MIN_LOAN', 0.00),
            'maximum_loan' => env('LOAN_LIMIT_GOLD', 1500.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_GOLD', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_GOLD', 27.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_GOLD', implode(',', range(7, 30)))),
            'eligibility_rules' => [],
        ],
        'platinum' => [
            'name' => env('LOAN_TIER_PLATINUM_NAME', 'platinum'),
            'trust_score' => [
                'min' => env('LOAN_TIER_PLATINUM_MIN', 85.00),
                'max' => env('LOAN_TIER_PLATINUM_MAX', 100.00),
            ],
            'minimum_loan' => env('LOAN_TIER_PLATINUM_MIN_LOAN', 0.00),
            'maximum_loan' => env('LOAN_LIMIT_PLATINUM', 2500.00),
            'platform_fee_percent' => env('LOAN_PLATFORM_FEE_PLATINUM', 5.00),
            'lender_return_percent' => env('LOAN_LENDER_RETURN_PLATINUM', 25.00),
            'allowed_durations' => $durations(env('LOAN_DURATIONS_PLATINUM', implode(',', range(7, 30)))),
            'eligibility_rules' => [],
        ],
    ],

    'risk_levels' => [
        'high' => ['min' => 0.00, 'max' => 49.99],
        'medium' => ['min' => 50.00, 'max' => 75.00],
        'low' => ['min' => 76.00, 'max' => 100.00],
    ],

    'affordability' => [
        'max_repayment_to_income_percent' => env('LOAN_MAX_REPAYMENT_INCOME_PERCENT', 30.00),
        'dti_excellent_max' => 20.00,
        'dti_good_max' => 35.00,
        'dti_fair_max' => 50.00,
        'auto_approve_min_score' => env('LOAN_AUTO_APPROVE_MIN_SCORE', 75.00),
        'auto_reject_max_score' => env('LOAN_AUTO_REJECT_MAX_SCORE', 30.00),
        'weight_dti' => 30,
        'weight_trust_score' => 25,
        'weight_repayment_history' => 20,
        'weight_disposable_income' => 15,
        'weight_bank_stability' => 10,
    ],

    'repayment' => [
        'penalty_rate_weekly' => env('LOAN_PENALTY_RATE_WEEKLY', 0.05),
        'max_penalty_ratio' => env('LOAN_MAX_PENALTY_RATIO', 0.50),
    ],

    'marketplace' => [
        'min_funding_amount' => env('LOAN_MIN_FUNDING_AMOUNT', 500.00),
        'min_funding_percent' => env('LOAN_MIN_FUNDING_PERCENT', 100.00),
    ],

    'agreement' => [
        'disk' => env('LOAN_AGREEMENT_DISK', 'local'),
        'version' => env('LOAN_AGREEMENT_VERSION', '1.0'),
        'terms' => 'The borrower agrees to repay the total repayment amount by the repayment date shown in this agreement.',
        'conditions' => 'This agreement is subject to successful loan approval, funding, identity verification, and all applicable platform policies and laws.',
    ],
];
