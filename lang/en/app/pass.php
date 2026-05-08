<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'pass' => 'Pass',
        ],
        'label' => [
            'client' => 'Client',
            'name' => 'Name',
            'name_placeholder' => '8-ride pass',
            'total_uses' => 'Total rides',
            'remaining_uses' => 'Remaining',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'price' => 'Pass price',
            'cancellation_policy_hours' => 'Cancellation policy (h)',
            'cancellation_policy_placeholder' => 'use stable default',
            'status' => 'Status',
            'notes' => 'Notes',
        ],
        'helper' => [
            'remaining_uses' => 'Auto-updated by the system; change manually only in exceptional cases.',
            'cancellation_policy_hours' => 'Cancellation X hours before the lesson = free (pass restored).',
        ],
    ],

    'table' => [
        'column' => [
            'client' => 'Client',
            'name' => 'Pass',
            'remaining_uses' => 'Remaining',
            'status' => 'Status',
            'valid_until' => 'Valid until',
            'price' => 'Price',
            'cancellation_policy' => 'Cancel policy',
            'cancellation_policy_default' => 'stable default',
            'created_at' => 'Issued',
        ],
        'filter' => [
            'client' => 'Client',
        ],
    ],
];
