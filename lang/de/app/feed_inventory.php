<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'item' => 'Futterposition',
        ],
        'label' => [
            'name' => 'Name',
            'unit' => 'Einheit',
            'low_stock_threshold' => 'Alarmschwelle',
            'sort_order' => 'Reihenfolge',
            'is_active' => 'Aktiv',
            'notes' => 'Notizen',
            'kind' => 'Bewegungsart',
            'amount' => 'Menge (positiv)',
            'movement_date' => 'Datum',
            'movement_notes' => 'Bewegungs-Notizen',
        ],
        'helper' => [
            'low_stock_threshold' => 'Bestand unter diesem Wert löst einen Alarm aus. Leer = kein Alarm.',
            'amount' => 'Positiven Wert eingeben — die Richtung ergibt sich aus der Bewegungsart.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Name',
            'current_stock' => 'Bestand',
            'low_stock_threshold' => 'Schwelle',
            'is_active' => 'Aktiv',
            'updated_at' => 'Letzte Bewegung',
        ],
        'filter' => [
            'low_stock' => 'Mit Alarmschwelle',
        ],
    ],
    'actions' => [
        'add_movement' => '+ Lagerbewegung',
    ],
    'kind' => [
        'purchase' => 'Zugang / Lieferung',
        'consumption' => 'Ausgabe / Verbrauch',
        'adjustment' => 'Inventurkorrektur',
        'waste' => 'Abschreibung / Entsorgung',
    ],
    'movements' => [
        'heading' => 'Bewegungshistorie',
        'col_date' => 'Datum',
        'col_kind' => 'Typ',
        'col_amount' => 'Änderung',
        'col_notes' => 'Notizen',
    ],
];
