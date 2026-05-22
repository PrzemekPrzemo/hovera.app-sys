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
        'connect' => [
            'label' => 'Link to stable',
            'stable_label' => 'Stable',
            'stable_helper' => 'Type the name — we show verified stables using Hovera.',
            'modal_heading' => 'Link "":horse"" to a stable',
            'modal_description' => 'Send a boarding request. The stable will see it in their panel and click "Accept" — then they will start logging visits, shoeings, lessons for your horse.',
            'notify_invalid_stable' => 'Selected stable is unavailable.',
            'notify_no_central' => 'This horse is not yet synced with the central registry — edit it and save again.',
            'notify_requested_title' => 'Request sent',
            'notify_requested_body' => 'The stable ":stable" will receive a notification. After they accept, the horse will appear in their panel.',
            'notify_already_active_title' => 'Horse is already linked',
            'notify_already_active_body' => 'Boarding with stable ":stable" is already active.',
        ],
    ],
];
