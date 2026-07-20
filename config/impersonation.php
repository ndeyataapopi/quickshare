<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Impersonation Settings
    |--------------------------------------------------------------------------
    |
    | Routes that should be blocked while an administrator is impersonating
    | a client account.
    |
    */

    'blocked_route_names' => [
        'client.profile.update',
        'client.profile.destroy',
        'password.update',
    ],
];
