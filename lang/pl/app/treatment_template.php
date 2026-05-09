<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'template' => 'Szablon zabiegu',
        ],
        'label' => [
            'name' => 'Nazwa',
            'type' => 'Typ zabiegu',
            'interval_days' => 'Częstotliwość (dni)',
            'sort_order' => 'Kolejność',
            'default_summary' => 'Domyślny opis',
            'default_notes' => 'Domyślne notatki',
            'is_active' => 'Aktywny',
        ],
        'helper' => [
            'interval_days' => 'Liczba dni do następnej wizyty. Pusto = jednorazowy zabieg bez kolejnego terminu.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'type' => 'Typ',
            'interval' => 'Co ile',
            'is_active' => 'Aktywny',
        ],
        'days' => 'dni',
    ],
];
