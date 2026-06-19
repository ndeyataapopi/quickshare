<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mifos X Integration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('MIFOS_ENABLED', false),

    'base_url' => env('MIFOS_BASE_URL', 'https://demo.mifos.io'),

    'tenant' => env('MIFOS_TENANT', 'default'),

    'auth' => [
        'username' => env('MIFOS_USERNAME'),
        'password' => env('MIFOS_PASSWORD'),
    ],

    'office_id' => env('MIFOS_OFFICE_ID', 1),

    'product_id' => env('MIFOS_PRODUCT_ID', 1),

    'client_mapping' => [
        'source' => 'external_id', // maps QuickShare user to Mifos client
    ],

    'webhook' => [
        'secret' => env('MIFOS_WEBHOOK_SECRET'),
        'allowed_ips' => [],
    ],

    'sync' => [
        'auto_push_loan' => true,
        'auto_pull_status' => true,
        'reconcile_schedule' => '0 2 * * *', // daily at 2 AM
    ],
];
