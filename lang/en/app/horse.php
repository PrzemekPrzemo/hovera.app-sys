<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Mare',
        'stallion' => 'Stallion',
        'gelding' => 'Gelding',
        'filly' => 'Filly',
        'colt' => 'Colt',
        'foal' => 'Foal',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'characteristics' => 'Characteristics',
            'boarding' => 'Boarding — billable services',
            'boarding_description' => 'Pick which pricing items apply to this horse. The client sees them in the portal with the monthly estimate.',
            'notes' => 'Notes',
        ],
        'label' => [
            'name' => 'Name',
            'owner' => 'Owner',
            'owner_placeholder' => '— stable —',
            'box' => 'Box',
            'box_placeholder' => '— unassigned —',
            'microchip' => 'Microchip',
            'passport_number' => 'Passport no.',
            'ueln' => 'UELN',
            'sex' => 'Sex',
            'breed' => 'Breed',
            'color' => 'Color',
            'birth_date' => 'Birth date',
            'boarding_services' => 'Pricing items',
        ],
        'helper' => [
            'box' => 'Changing the box logs history in "Boxes → Assignment history".',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Configure pricing in "Stable → Boarding pricing". Per-horse price overrides (e.g. discount) are set there manually after creating the entry.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'breed' => 'Breed',
            'sex' => 'Sex',
            'color' => 'Color',
            'birth_date' => 'Born',
            'owner' => 'Owner',
            'owner_placeholder' => '— stable —',
            'created_at' => 'Added',
        ],
    ],
];
