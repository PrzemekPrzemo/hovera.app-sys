<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Manège couvert',
        'outdoor' => 'Carrière',
        'paddock' => 'Paddock',
        'lunge' => 'Rond de longe',
        'field' => 'Terrain',
    ],

    'form' => [
        'label' => [
            'name' => 'Nom',
            'type' => 'Type',
            'color' => 'Couleur dans le calendrier',
            'is_active' => 'Actif',
            'sort_order' => 'Ordre',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom',
            'type' => 'Type',
            'color' => 'Couleur',
            'is_active' => 'Actif',
        ],
    ],
];
