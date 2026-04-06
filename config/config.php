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
            'owner' => env('GITHUB_OWNER'),
            'repo' => env('GITHUB_REPO'),
            'token' => env('GITHUB_TOKEN'),
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

    'commands' => [
        'prep' => [],

        'build' => [
            // 'app:desktop-default-data' // just an example command
        ],

        'ship' => [
            // 'app:desktop-default-data'
        ]
    ],

    /**
     * When using desktop with a corresponding web version -
     * bidirection if true will allow remote data to be pushed to local when there is internet.
     * @todo Currently does not work bidrection
     */
    'syncs' => [
        'bidirection' => env('SLIMER_DESKTOP_SYN_BIDIRECTION', false),

        'table_relations' => [
            // 'product_otpions' => [
            //     'product_id' => [
            //         'table' => 'products',
            //         'column' => 'product_uid',
            //     ],
            // ],
        ],
    ],

];
