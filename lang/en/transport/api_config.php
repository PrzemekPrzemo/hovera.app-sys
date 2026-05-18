<?php

declare(strict_types=1);

return [
    'action' => [
        'test_key' => 'Test API key',
    ],

    'notify' => [
        'success' => 'Key works',
        'failure' => 'Key does not work',
    ],

    'probe' => [
        'empty_key' => 'Paste an API key before clicking "Test".',
        'ok' => 'Key :provider returns a valid route (test distance: :km km).',
        'unexpected_error' => 'Unexpected error',
    ],
];
