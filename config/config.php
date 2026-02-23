<?php


return [
    'enabled' => env('SLIMER_DESKTOP_ENABLED', false),

    'app' => [
        'mode' => env('SLIMER_DESKTOP_APP_MODE'),
        'channel' => env('SLIMER_DESKTOP_APP_CHANNEL'),
        'role' => env('SLIMER_DESKTOP_APP_ROLE'),
        'setup' => env('SLIMER_DESKTOP_SETUP'),
        'is_desktop' => env('SLIMER_IS_DESKTOP'),
    ],

    'release' => [
        'github' => [
            'owner' => 'GITHUB_OWNER',
            'repo' => 'GITHUB_REPO',
            'token' => 'GITHUB_RELEASE_TOKEN',
        ]
    ],

    'api' => [
        'base' => env('SLIMER_DESKTOP_API_BASE'),
        'secret' => env('SLIMER_DESKTOP_API_TOKEN'),
    ],

    'jwt' => [
        'secret' => env('SLIMER_JWT_SECRET'),
        'iss' => env('SLIMER_JWT_ISS'),
    ],

    'landlord' => [
        'domain' => '',
        'model' => '',
        'connection' => env('SLIMER_LANDLORD_CONNECTION', 'pgsql'),

    ],

    'tenant' => [
        'key' => env('SLIMER_DESKTOP_TENANT_KEY')
    ],

];
