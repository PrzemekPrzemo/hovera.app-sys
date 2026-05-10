<?php

declare(strict_types=1);

return [
    // APNs: production credentials live in env. Use the .p8 key (token-based).
    'apn' => [
        'enabled' => (bool) env('PUSH_APN_ENABLED', false),
        'key_id' => env('PUSH_APN_KEY_ID'),
        'team_id' => env('PUSH_APN_TEAM_ID'),
        'private_key' => env('PUSH_APN_PRIVATE_KEY'),
        'app_bundle_id' => env('PUSH_APN_BUNDLE_ID', 'app.hovera.ios'),
        'production' => (bool) env('PUSH_APN_PRODUCTION', false),
    ],

    // FCM via kreait/laravel-firebase. Set FIREBASE_CREDENTIALS to JSON path.
    'fcm' => [
        'enabled' => (bool) env('PUSH_FCM_ENABLED', false),
    ],
];
