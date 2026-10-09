<?php

return [

    /*
    | "reverb" turns on live updates (see docs/REALTIME.md). "log" or "null" keeps everything on normal polling.
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            // How THIS server reaches Reverb.
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            // Never let a slow websocket server hold up a ticket action.
            'client_options' => [
                'timeout' => 3,
                'connect_timeout' => 2,
            ],
            // How the BROWSER reaches Reverb (same as above unless you put a proxy in front).
            'client' => [
                'host' => env('REVERB_PUBLIC_HOST', env('REVERB_HOST')),
                'port' => env('REVERB_PUBLIC_PORT', env('REVERB_PORT', 443)),
                'scheme' => env('REVERB_PUBLIC_SCHEME', env('REVERB_SCHEME', 'https')),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
