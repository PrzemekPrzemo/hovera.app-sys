<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'template' => 'Behandlungsvorlage',
        ],
        'label' => [
            'name' => 'Name',
            'type' => 'Behandlungstyp',
            'interval_days' => 'Intervall (Tage)',
            'sort_order' => 'Reihenfolge',
            'default_summary' => 'Standardbeschreibung',
            'default_notes' => 'Standardnotizen',
            'is_active' => 'Aktiv',
        ],
        'helper' => [
            'interval_days' => 'Anzahl der Tage bis zur nächsten Behandlung. Leer = einmalige Behandlung ohne Folgetermin.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Typ',
            'interval' => 'Alle',
            'is_active' => 'Aktiv',
        ],
        'days' => 'Tage',
    ],
];
