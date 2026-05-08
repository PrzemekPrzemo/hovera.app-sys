<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Instructor data',
        ],
        'label' => [
            'name' => 'Full name',
            'phone' => 'Phone',
            'hourly_rate' => 'Hourly rate',
            'color' => 'Calendar color',
            'is_active' => 'Active',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Full name',
            'phone' => 'Phone',
            'hourly_rate' => 'Rate',
            'color' => 'Color',
            'is_active' => 'Active',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],
];
