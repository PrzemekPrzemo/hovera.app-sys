<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Hala kryta',
        'outdoor' => 'Plac otwarty',
        'paddock' => 'Padok',
        'lunge' => 'Lonżownik',
        'field' => 'Teren',
    ],

    'form' => [
        'label' => [
            'name' => 'Nazwa',
            'type' => 'Typ',
            'color' => 'Kolor w kalendarzu',
            'is_active' => 'Aktywna',
            'sort_order' => 'Kolejność',
            'notes' => 'Notatki',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'type' => 'Typ',
            'color' => 'Kolor',
            'is_active' => 'Aktywna',
        ],
    ],
];
