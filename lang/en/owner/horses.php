<?php

declare(strict_types=1);

return [
    'navigation' => 'My horses',

    'model' => [
        'singular' => 'horse',
        'plural' => 'horses',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'notes' => 'Notes',
        ],
        'label' => [
            'name' => 'Name',
            'breed' => 'Breed',
            'birth_date' => 'Date of birth',
            'sex' => 'Sex',
            'color' => 'Colour',
            'passport_number' => 'Passport number',
            'microchip' => 'Microchip',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'name' => 'Name',
        'breed' => 'Breed',
        'birth_date' => 'Date of birth',
        'sex' => 'Sex',
        'passport_number' => 'Passport',
    ],

    'sex' => [
        'mare' => 'Mare',
        'stallion' => 'Stallion',
        'gelding' => 'Gelding',
        'filly' => 'Filly',
        'colt' => 'Colt',
        'foal' => 'Foal',
    ],

    'empty' => [
        'heading' => 'No horses in your records',
        'description' => 'Add your first horse to make booking transport faster.',
    ],

    'action' => [
        'order_transport' => 'Order transport for this horse',
    ],
];
