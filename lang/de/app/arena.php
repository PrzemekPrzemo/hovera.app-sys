<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Reithalle',
        'outdoor' => 'Außenplatz',
        'paddock' => 'Paddock',
        'lunge' => 'Longierplatz',
        'field' => 'Gelände',
    ],

    'form' => [
        'label' => [
            'name' => 'Name',
            'type' => 'Typ',
            'color' => 'Farbe im Kalender',
            'is_active' => 'Aktiv',
            'sort_order' => 'Reihenfolge',
            'notes' => 'Notizen',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Typ',
            'color' => 'Farbe',
            'is_active' => 'Aktiv',
        ],
    ],
];
