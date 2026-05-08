<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Box wewnętrzny',
        'paddock' => 'Padok',
        'outdoor' => 'Box zewnętrzny',
        'quarantine' => 'Kwarantanna',
    ],
    'types_short' => [
        'indoor' => 'Wewnętrzny',
        'paddock' => 'Padok',
        'outdoor' => 'Zewnętrzny',
        'quarantine' => 'Kwarantanna',
    ],

    'form' => [
        'section' => [
            'box' => 'Box',
            'notes' => 'Notatki',
        ],
        'label' => [
            'name' => 'Nazwa / numer',
            'label_short' => 'Krótki kod (np. "12")',
            'type' => 'Typ',
            'size_m2' => 'Rozmiar (m²)',
            'capacity' => 'Pojemność',
            'monthly_rate' => 'Miesięczna cena pensjonatu',
            'is_active' => 'Aktywny',
            'sort_order' => 'Kolejność',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'capacity' => 'Ile koni może być w tym boksie (zwykle 1; większe boksy grupowe mogą mieć więcej).',
            'monthly_rate' => 'Domyślna stawka — można jeszcze override per koń lub klient.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'type' => 'Typ',
            'size_m2' => 'm²',
            'status' => 'Status',
            'horse_sex' => 'Płeć konia',
            'monthly_rate' => 'Pensjonat',
            'is_active' => 'Aktywny',
        ],
        'status' => [
            'free' => 'Wolny',
            'occupied' => 'Zajęty',
        ],
        'filter' => [
            'vacant' => 'Tylko wolne',
            'only_active' => 'Tylko aktywne',
        ],
    ],
];
