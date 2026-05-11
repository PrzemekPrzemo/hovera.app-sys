<?php

declare(strict_types=1);

return [
    'vat_rates' => [
        '23' => '23 %',
        '8' => '8 %',
        '5' => '5 %',
        '0' => '0 %',
        'zw' => 'befreit',
        'np' => 'n. anwendbar',
    ],

    'form' => [
        'section' => [
            'service' => 'Leistung in der Preisliste',
            'service_description' => 'Diese Leistungen wählen Sie pro Pferd aus (Reiter „Pension" in der Pferdekarte). Sie erscheinen im Kundenportal — der Besitzer sieht, wofür er zahlt.',
        ],
        'label' => [
            'name' => 'Name',
            'name_placeholder' => 'z. B. Heu, Boxenreinigung, Turniertransport',
            'description' => 'Beschreibung (optional)',
            'unit' => 'Einheit',
            'unit_placeholder' => 'Stk. / kg / Std. / Mon.',
            'frequency' => 'Abrechnungsfrequenz',
            'price_net' => 'Nettopreis',
            'vat_rate' => 'MwSt.-Satz',
            'is_active' => 'Aktiv',
            'sort_order' => 'Reihenfolge',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'frequency' => 'Frequenz',
            'price_net' => 'Nettopreis',
            'vat' => 'MwSt.',
            'horses_count' => 'Pferde',
            'is_active' => 'Aktiv',
        ],
    ],
];
