<?php

declare(strict_types=1);

return [
    'navigation' => 'POI library',

    'model' => [
        'singular' => 'POI',
        'plural' => 'POI library',
    ],

    'section' => [
        'basic' => 'Basics',
        'metadata' => 'Metadata',
    ],

    'form' => [
        'label' => [
            'name' => 'Name',
            'kind' => 'Type',
            'address' => 'Address',
            'notes' => 'Notes',
            'is_active' => 'Active',
            'sort_order' => 'Order',
        ],
        'helper' => [
            'address' => 'We geocode the address on save — so you can use the POI in quotes without re-entering coordinates.',
        ],
    ],

    'kind' => [
        'base' => 'Carrier base',
        'stable' => 'Stable',
        'parking' => 'Parking',
        'fuel' => 'Fuel station',
        'other' => 'Other',
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'kind' => 'Type',
            'address' => 'Address',
            'is_active' => 'Active',
        ],
    ],

    'empty' => [
        'heading' => 'POI library is empty',
        'description' => 'Add your bases, boarding stables, truck-friendly parking — they will be suggested when building quotes.',
    ],

    'notify' => [
        'geocoding_failed_title' => 'Could not recognise POI address',
    ],
];
