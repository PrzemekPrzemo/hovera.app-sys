<?php

declare(strict_types=1);

return [
    'navigation' => 'Boarding requests',

    'model' => [
        'singular' => 'Boarding request',
        'plural' => 'Boarding requests',
    ],

    'table' => [
        'column' => [
            'horse' => 'Horse',
            'owner' => 'Owner',
            'requested_at' => 'Requested at',
        ],
        'passport_prefix' => 'Passport:',
        'no_passport' => 'No passport',
    ],

    'empty' => [
        'heading' => 'No pending requests',
        'description' => 'When a horse owner sends a boarding request from their panel, it will appear here.',
    ],

    'action' => [
        'accept' => [
            'label' => 'Accept',
            'modal_description' => 'You accept boarding for horse ":horse" owned by :owner. From now on the horse appears in your panel and you can assign a box and issue invoices.',
            'success' => 'Boarding accepted',
            'success_body' => 'Horse ":horse" is now in "Horses". You can assign a box and set up pricing.',
            'stable_missing' => 'Stable not found — refresh the page.',
        ],
        'reject' => [
            'label' => 'Decline',
            'reason_label' => 'Reason (visible to the owner)',
            'from_stable' => '(rejected by stable :stable)',
            'success' => 'Request declined — the owner was notified.',
        ],
    ],
];
