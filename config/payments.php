<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QuickShare Receiving Accounts
    |--------------------------------------------------------------------------
    |
    | These are the bank accounts and mobile wallets that funders use to
    | transfer money into QuickShare. Configuration is loaded from the
    | environment so account details are never hardcoded in the codebase.
    |
    */

    'default_reference_prefix' => env('PAYMENT_REFERENCE_PREFIX', 'QS-LOAN'),

    'banks' => [
        'fnb_namibia' => [
            'name' => 'FNB Namibia',
            'account_name' => env('BANK_FNB_ACCOUNT_NAME','QuickShare'),
            'account_number' => env('BANK_FNB_ACCOUNT_NUMBER','62273630684'),
            'branch_code' => env('BANK_FNB_BRANCH_CODE','282672'),
            'branch_name' => env('BANK_FNB_BRANCH_NAME', 'Grove Mall'),
            'reference_prefix' => env('BANK_FNB_REFERENCE_PREFIX', 'QS-LOAN'),
        ],
        'standard_bank_namibia' => [
            'name' => 'Standard Bank Namibia',
            'account_name' => env('BANK_STANDARD_ACCOUNT_NAME','QuickShare'),
            'account_number' => env('BANK_STANDARD_ACCOUNT_NUMBER','549044086'),
            'branch_code' => env('BANK_STANDARD_BRANCH_CODE','082672'),
            'branch_name' => env('BANK_STANDARD_BRANCH_NAME', 'Ausspannplatz'),
            'reference_prefix' => env('BANK_STANDARD_REFERENCE_PREFIX', 'QS-LOAN'),
        ],
        'nedbank_namibia' => [
            'name' => 'Nedbank Namibia',
            'account_name' => env('BANK_NEDBANK_ACCOUNT_NAME','QuickShare'),
            'account_number' => env('BANK_NEDBANK_ACCOUNT_NUMBER','11990147886'),
            'branch_code' => env('BANK_NEDBANK_BRANCH_CODE','461029'),
            'branch_name' => env('BANK_NEDBANK_BRANCH_NAME', 'Windhoek South'),
            'reference_prefix' => env('BANK_NEDBANK_REFERENCE_PREFIX', 'QS-LOAN'),
        ],
        // 'bank_windhoek' => [
        //     'name' => 'Bank Windhoek',
        //     'account_name' => env('BANK_BANKWINDHOEK_ACCOUNT_NAME'),
        //     'account_number' => env('BANK_BANKWINDHOEK_ACCOUNT_NUMBER'),
        //     'branch_code' => env('BANK_BANKWINDHOEK_BRANCH_CODE'),
        //     'branch_name' => env('BANK_BANKWINDHOEK_BRANCH_NAME', 'Bank Windhoek'),
        //     'reference_prefix' => env('BANK_BANKWINDHOEK_REFERENCE_PREFIX', 'QS-LOAN'),
        // ],
    ],

    'wallets' => [
        'fnb_ewallet' => [
            'name' => 'FNB eWallet',
            'provider' => 'FNB Namibia',
            'cellphone' => env('WALLET_FNB_CELLPHONE','0814686622'),
            'instructions' => env('WALLET_FNB_INSTRUCTIONS', 'Send an eWallet to the number above.'),
        ],
        'standard_bluevoucher' => [
            'name' => 'Standard Bank BlueVoucher',
            'provider' => 'Standard Bank Namibia',
            'cellphone' => env('WALLET_STANDARD_CELLPHONE','0814686622'),
            'instructions' => env('WALLET_STANDARD_INSTRUCTIONS', 'Send a BlueVoucher to the number above.'),
        ],
        'nedbank_mobimoney' => [
            'name' => 'Nedbank MobiMoney',
            'provider' => 'Nedbank Namibia',
            'cellphone' => env('WALLET_NEDBANK_CELLPHONE','0814686622'),
            'instructions' => env('WALLET_NEDBANK_INSTRUCTIONS', 'Transfer MobiMoney to the number above.'),
        ],
        'bank_windhoek_easywallet' => [
            'name' => 'Bank Windhoek EasyWallet',
            'provider' => 'Bank Windhoek',
            'cellphone' => env('WALLET_BANKWINDHOEK_CELLPHONE','0814686622'),
            'instructions' => env('WALLET_BANKWINDHOEK_INSTRUCTIONS', 'Send an EasyWallet to the number above.'),
        ],
    ],

    'cash_deposit' => [
        'default_bank' => env('CASH_DEPOSIT_BANK', 'fnb_namibia'),
        'instructions' => env(
            'CASH_DEPOSIT_INSTRUCTIONS',
            'Give cash to the recipient.'
        ),
    ],
];
