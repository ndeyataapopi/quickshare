<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    | Currency abbreviation used throughout the platform.
    */
    'currency' => env('LOAN_CURRENCY', 'NAD'),
    'currency_symbol' => env('LOAN_CURRENCY_SYMBOL', 'N$'),

    /*
    |--------------------------------------------------------------------------
    | Interest Rate
    |--------------------------------------------------------------------------
    | Annual interest rate as a percentage. Applied proportionally based
    | on loan_term_days.
    */
    'interest_rate' => env('LOAN_INTEREST_RATE', 30.00),

    /*
    |--------------------------------------------------------------------------
    | Platform Fee
    |--------------------------------------------------------------------------
    | One-time platform fee as a percentage of the approved loan amount.
    */
    'platform_fee_percent' => env('LOAN_PLATFORM_FEE_PERCENT', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Loan Term Limits (days)
    |--------------------------------------------------------------------------
    */
    'min_term_days' => env('LOAN_MIN_TERM_DAYS', 7),
    'max_term_days' => env('LOAN_MAX_TERM_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Loan Amount Limits
    |--------------------------------------------------------------------------
    | Global limits. Trust-score-based limits may further restrict the max.
    */
    'min_amount' => env('LOAN_MIN_AMOUNT', 500.00),
    'max_amount' => env('LOAN_MAX_AMOUNT', 1500.00),

    /*
    |--------------------------------------------------------------------------
    | Affordability
    |--------------------------------------------------------------------------
    | Maximum number of concurrent active/disbursed loans per borrower.
    */
    'max_active_loans' => env('LOAN_MAX_ACTIVE_LOANS', 3),

    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    | Minimum funding percentage before a loan can be disbursed.
    */
    'min_funding_percent' => env('LOAN_MIN_FUNDING_PERCENT', 100.00),

    /*
    |--------------------------------------------------------------------------
    | Affordability Assessment
    |--------------------------------------------------------------------------
    */
    'affordability' => [
        // Max percentage of disposable income that can go to loan repayments
        'max_repayment_to_income_percent' => env('LOAN_MAX_REPAYMENT_INCOME_PERCENT', 30.00),

        // DTI ratio thresholds
        'dti_excellent_max' => 20.00,
        'dti_good_max' => 35.00,
        'dti_fair_max' => 50.00,
        // Above fair = poor

        // Affordability score thresholds for auto-decisions
        'auto_approve_min_score' => env('LOAN_AUTO_APPROVE_MIN_SCORE', 75.00),
        'auto_reject_max_score' => env('LOAN_AUTO_REJECT_MAX_SCORE', 30.00),

        // Score component weights (must sum to 100)
        'weight_dti' => 30,
        'weight_trust_score' => 25,
        'weight_repayment_history' => 20,
        'weight_disposable_income' => 15,
        'weight_bank_stability' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Funding
    |--------------------------------------------------------------------------
    */
    'min_funding_amount' => env('LOAN_MIN_FUNDING_AMOUNT', 500.00),

    /*
    |--------------------------------------------------------------------------
    | Trust Tier Loan Limits
    |--------------------------------------------------------------------------
    | Maximum loan amount allowed per trust score tier. The effective max is
    | min(global max_amount, tier_limit) as enforced by LoanService.
    */
    'tier_limits' => [
        'bronze'   => env('LOAN_LIMIT_BRONZE',   500.00),
        'silver'   => env('LOAN_LIMIT_SILVER',   1000.00),
        'gold'     => env('LOAN_LIMIT_GOLD',     1500.00),
        'platinum' => env('LOAN_LIMIT_PLATINUM', 2500.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trust Score Thresholds
    |--------------------------------------------------------------------------
    */
    'trust_score' => [
        'min_borrow_score'   => env('LOAN_MIN_BORROW_SCORE', 30.00),
        'tier_platinum_min'  => env('LOAN_TIER_PLATINUM_MIN', 85.00),
        'tier_gold_min'      => env('LOAN_TIER_GOLD_MIN', 70.00),
        'tier_silver_min'    => env('LOAN_TIER_SILVER_MIN', 50.00),
    ],

];
