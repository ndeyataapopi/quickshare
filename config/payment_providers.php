<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Provider Defaults
    |--------------------------------------------------------------------------
    |
    | Global fallback values used when an operation-specific configuration
    | does not set its own provider or mode.
    |
    */

    'default_provider' => env('PAYMENT_PROVIDER_DEFAULT', 'manual'),

    'execution_mode' => env('PAYMENT_EXECUTION_MODE', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Global Automation Kill Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, every automated payment operation behaves as manual,
    | regardless of per-operation settings. This is the global emergency stop
    | for live payment automation.
    |
    */

    'automation_enabled' => env('PAYMENT_AUTOMATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Per-Operation Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Each of the four money-movement operations has its own independent
    | payment method, execution mode, provider, and enablement switch. All
    | default to manual/disabled so existing QuickShare behaviour is preserved.
    |
    */

    'operations' => [
        'lender_funding' => [
            'enabled' => env('LENDER_FUNDING_ENABLED', false),
            'method' => env('LENDER_FUNDING_METHOD', 'manual'),
            'mode' => env('LENDER_FUNDING_MODE', 'manual'),
            'provider' => env('LENDER_FUNDING_PROVIDER', 'manual'),
        ],

        'borrower_disbursement' => [
            'enabled' => env('BORROWER_DISBURSEMENT_ENABLED', false),
            'method' => env('BORROWER_DISBURSEMENT_METHOD', 'manual'),
            'mode' => env('BORROWER_DISBURSEMENT_MODE', 'manual'),
            'provider' => env('BORROWER_DISBURSEMENT_PROVIDER', 'manual'),
        ],

        'borrower_repayment' => [
            'enabled' => env('BORROWER_REPAYMENT_ENABLED', false),
            'method' => env('BORROWER_REPAYMENT_METHOD', 'manual'),
            'mode' => env('BORROWER_REPAYMENT_MODE', 'manual'),
            'provider' => env('BORROWER_REPAYMENT_PROVIDER', 'manual'),
        ],

        'lender_returns' => [
            'enabled' => env('LENDER_RETURNS_ENABLED', false),
            'method' => env('LENDER_RETURNS_METHOD', 'manual'),
            'mode' => env('LENDER_RETURNS_MODE', 'manual'),
            'provider' => env('LENDER_RETURNS_PROVIDER', 'manual'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Payment Providers
    |--------------------------------------------------------------------------
    |
    | Each driver maps to a provider class. The 'manual' driver is always
    | available and never moves money. The 'fake' driver is for local/testing
    | environments only and must never be used in production.
    |
    */

    'providers' => [
        'manual' => [
            'driver' => 'manual',
        ],

        'fake' => [
            'driver' => 'fake',

            // Default simulated outcome: success, pending, failed, timeout,
            // reversed, duplicate, webhook_duplicate
            'outcome' => env('FAKE_PAYMENT_OUTCOME', 'success'),
        ],

        'collexia' => [
            'driver' => 'collexia',
            'base_url' => env('COLLEXIA_BASE_URL'),
            'api_key' => env('COLLEXIA_API_KEY'),
            'client_code' => env('COLLEXIA_CLIENT_CODE'),
            'sandbox' => env('COLLEXIA_SANDBOX', true),
            'timeout' => env('COLLEXIA_TIMEOUT', 30),
            'connection_timeout' => env('COLLEXIA_CONNECTION_TIMEOUT', 5),
            'webhook_secret' => env('COLLEXIA_WEBHOOK_SECRET'),
            'signature_header' => env('COLLEXIA_SIGNATURE_HEADER', 'X-Webhook-Signature'),
            'signature_algorithm' => env('COLLEXIA_SIGNATURE_ALGORITHM', 'hmac-sha256'),
            'health_endpoint' => env('COLLEXIA_HEALTH_ENDPOINT'),

            // Endpoints are configurable because Collexia does not publish
            // open API documentation. These defaults are inferred placeholders
            // and must be validated against actual Collexia sandbox evidence.
            'endpoints' => [
                'disbursement' => env('COLLEXIA_DISBURSEMENT_ENDPOINT', '/api/v1/payments'),
                'lender_return' => env('COLLEXIA_LENDER_RETURN_ENDPOINT', '/api/v1/payments'),
                'repayment' => env('COLLEXIA_REPAYMENT_ENDPOINT', '/api/v1/collections'),
                'status_check' => env('COLLEXIA_STATUS_CHECK_ENDPOINT', '/api/v1/transactions/{reference}'),
            ],

            // Capability matrix is derived from public Collexia product pages.
            // Only confirmed capabilities are enabled.
            'supported_methods' => [
                'borrower_disbursement' => ['bank_payout'],
                'borrower_repayment' => ['debit_order'],
                'lender_returns' => ['bank_payout'],
            ],
        ],

        'mobidebit' => [
            'driver' => 'mobidebit',
            'base_url' => env('MOBIDEBIT_BASE_URL'),
            'api_key' => env('MOBIDEBIT_API_KEY'),
            'sandbox' => env('MOBIDEBIT_SANDBOX', true),
            'timeout' => env('MOBIDEBIT_TIMEOUT', 30),
            'connection_timeout' => env('MOBIDEBIT_CONNECTION_TIMEOUT', 5),
            'redirect_url' => env('MOBIDEBIT_REDIRECT_URL'),
            'response_url' => env('MOBIDEBIT_RESPONSE_URL'),
            'webhook_secret' => env('MOBIDEBIT_WEBHOOK_SECRET'),
            'signature_header' => env('MOBIDEBIT_SIGNATURE_HEADER', 'X-Webhook-Signature'),
            'signature_algorithm' => env('MOBIDEBIT_SIGNATURE_ALGORITHM', 'hmac-sha256'),
            'health_endpoint' => env('MOBIDEBIT_HEALTH_ENDPOINT'),

            // Endpoints are configurable because Mobipaid/MobiDebit do not
            // publish a complete open API contract for Namibia. Defaults are
            // taken from the Mobipaid developer documentation and must be
            // validated against actual MobiDebit sandbox evidence.
            'endpoints' => [
                'payment_request' => env('MOBIDEBIT_PAYMENT_REQUEST_ENDPOINT', '/v2/payment-requests/'),
                'status_check' => env('MOBIDEBIT_STATUS_CHECK_ENDPOINT', '/v2/payment-requests/{reference}'),
            ],

            // Capability matrix is derived from public Mobipaid documentation.
            // Only confirmed capabilities are enabled.
            'supported_methods' => [
                'lender_funding' => ['payment_link'],
                'borrower_repayment' => ['payment_link', 'debit_order'],
            ],
        ],

        'realpay' => [
            'driver' => 'realpay',
            'base_url' => env('REALPAY_BASE_URL'),
            'api_key' => env('REALPAY_API_KEY'),
            'sandbox' => env('REALPAY_SANDBOX', true),
            'timeout' => env('REALPAY_TIMEOUT', 30),
            'connection_timeout' => env('REALPAY_CONNECTION_TIMEOUT', 5),
            'auth_header' => env('REALPAY_AUTH_HEADER', 'X-API-Key'),
            'webhook_secret' => env('REALPAY_WEBHOOK_SECRET'),
            'signature_header' => env('REALPAY_SIGNATURE_HEADER', 'X-Webhook-Signature'),
            'signature_algorithm' => env('REALPAY_SIGNATURE_ALGORITHM', 'hmac-sha256'),
            'health_endpoint' => env('REALPAY_HEALTH_ENDPOINT'),

            // RealPay product capabilities are confirmed by public product pages,
            // but exact API endpoint names are not published. Defaults are
            // standard REST conventions and must be validated against the
            // documentation supplied during RealPay onboarding.
            'endpoints' => [
                'collections' => env('REALPAY_COLLECTIONS_ENDPOINT', '/api/v1/collections'),
                'payouts' => env('REALPAY_PAYOUTS_ENDPOINT', '/api/v1/payouts'),
                'verification' => env('REALPAY_VERIFICATION_ENDPOINT', '/api/v1/verifications'),
                'status_check' => env('REALPAY_STATUS_CHECK_ENDPOINT', '/api/v1/transactions/{reference}'),
            ],

            // Capability matrix is derived from public RealPay product pages.
            // Only confirmed capabilities are enabled.
            'supported_methods' => [
                'lender_funding' => ['debit_order'],
                'borrower_disbursement' => ['bank_payout'],
                'borrower_repayment' => ['debit_order'],
                'lender_returns' => ['bank_payout'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Normalized Payment Methods
    |--------------------------------------------------------------------------
    |
    | This is a capability vocabulary. A provider may support any subset of
    | these methods for any subset of operations.
    |
    */

    'methods' => [
        'manual',
        'payment_link',
        'debit_order',
        'bank_payout',
        'wallet_payout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Money-Movement Operations
    |--------------------------------------------------------------------------
    |
    */

    'operation_names' => [
        'lender_funding',
        'borrower_disbursement',
        'borrower_repayment',
        'lender_returns',
    ],
];
