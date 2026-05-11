<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Innenbox',
        'paddock' => 'Paddock',
        'outdoor' => 'Außenbox',
        'quarantine' => 'Quarantäne',
    ],
    'types_short' => [
        'indoor' => 'Innen',
        'paddock' => 'Paddock',
        'outdoor' => 'Außen',
        'quarantine' => 'Quarantäne',
    ],

    'form' => [
        'section' => [
            'box' => 'Box',
            'notes' => 'Notizen',
        ],
        'label' => [
            'building' => 'Gebäude',
            'building_placeholder' => '— ohne Gebäude —',
            'name' => 'Name / Nummer',
            'label_short' => 'Kurzcode (z. B. „12")',
            'type' => 'Typ',
            'size_m2' => 'Größe (m²)',
            'capacity' => 'Kapazität',
            'monthly_rate' => 'Monatlicher Pensionspreis',
            'is_active' => 'Aktiv',
            'sort_order' => 'Reihenfolge',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'capacity' => 'Wie viele Pferde diese Box aufnehmen kann (meist 1; größere Gruppenboxen können mehr fassen).',
            'monthly_rate' => 'Standardpreis — kann pro Pferd oder Kunde überschrieben werden.',
        ],
    ],

    'table' => [
        'column' => [
            'building' => 'Gebäude',
            'building_none' => '— ohne Gebäude —',
            'name' => 'Name',
            'type' => 'Typ',
            'size_m2' => 'm²',
            'status' => 'Status',
            'horse_sex' => 'Pferdegeschlecht',
            'monthly_rate' => 'Pension',
            'is_active' => 'Aktiv',
        ],
        'status' => [
            'free' => 'Frei',
            'occupied' => 'Belegt',
        ],
        'filter' => [
            'building' => 'Gebäude',
            'vacant' => 'Nur freie',
            'only_active' => 'Nur aktive',
        ],
    ],
];
