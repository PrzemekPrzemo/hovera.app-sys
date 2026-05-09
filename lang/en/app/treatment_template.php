<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'template' => 'Treatment template',
        ],
        'label' => [
            'name' => 'Name',
            'type' => 'Treatment type',
            'interval_days' => 'Interval (days)',
            'sort_order' => 'Sort order',
            'default_summary' => 'Default summary',
            'default_notes' => 'Default notes',
            'is_active' => 'Active',
        ],
        'helper' => [
            'interval_days' => 'Days until the next visit. Leave empty for one-off treatments without a follow-up.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Type',
            'interval' => 'Every',
            'is_active' => 'Active',
        ],
        'days' => 'days',
    ],
];
