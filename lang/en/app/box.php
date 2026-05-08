<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Indoor box',
        'paddock' => 'Paddock',
        'outdoor' => 'Outdoor box',
        'quarantine' => 'Quarantine',
    ],
    'types_short' => [
        'indoor' => 'Indoor',
        'paddock' => 'Paddock',
        'outdoor' => 'Outdoor',
        'quarantine' => 'Quarantine',
    ],

    'form' => [
        'section' => [
            'box' => 'Box',
            'notes' => 'Notes',
        ],
        'label' => [
            'name' => 'Name / number',
            'label_short' => 'Short code (e.g. "12")',
            'type' => 'Type',
            'size_m2' => 'Size (m²)',
            'capacity' => 'Capacity',
            'monthly_rate' => 'Monthly boarding fee',
            'is_active' => 'Active',
            'sort_order' => 'Order',
            'notes' => 'Notes',
        ],
        'helper' => [
            'capacity' => 'How many horses fit in this box (usually 1; larger group boxes may have more).',
            'monthly_rate' => 'Default rate — can still be overridden per horse or client.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Type',
            'size_m2' => 'm²',
            'status' => 'Status',
            'horse_sex' => 'Horse sex',
            'monthly_rate' => 'Boarding',
            'is_active' => 'Active',
        ],
        'status' => [
            'free' => 'Free',
            'occupied' => 'Occupied',
        ],
        'filter' => [
            'vacant' => 'Free only',
            'only_active' => 'Only active',
        ],
    ],
];
