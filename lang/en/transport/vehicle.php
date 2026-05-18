<?php

declare(strict_types=1);

return [
    'section' => [
        'identification' => 'Identification',
        'capacity' => 'Capacity & weight',
        'equipment' => 'Equipment',
        'other' => 'Other',
    ],

    'form' => [
        'label' => [
            'name' => 'Vehicle name',
            'registration_plate' => 'Registration plate',
            'year_of_manufacture' => 'Year of manufacture',
            'capacity_horses' => 'Capacity (horses)',
            'gross_weight_kg' => 'Gross weight',
            'payload_kg' => 'Payload',
            'has_air_suspension' => 'Air suspension',
            'has_camera' => 'Horse-bay camera',
            'has_climate_control' => 'Climate control',
            'is_active' => 'Active',
            'sort_order' => 'Sort order',
            'notes' => 'Notes',
        ],
        'placeholder' => [
            'name' => 'e.g. Volvo FH16 — big rig',
        ],
        'suffix' => [
            'horses' => 'horses',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'registration_plate' => 'Plate',
            'capacity_horses' => 'Horses',
            'gross_weight_kg' => 'GVWR',
            'is_active' => 'Active',
        ],
    ],
];
