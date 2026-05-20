<?php

declare(strict_types=1);

return [
    'section' => [
        'identification' => 'Identifikation',
        'capacity' => 'Kapazität & Gewicht',
        'equipment' => 'Ausstattung',
        'other' => 'Sonstiges',
    ],

    'form' => [
        'label' => [
            'vehicle_type' => 'Fahrzeugtyp',
            'name' => 'Fahrzeugname',
            'registration_plate' => 'Kennzeichen',
            'year_of_manufacture' => 'Baujahr',
            'capacity_horses' => 'Kapazität (Pferde)',
            'gross_weight_kg' => 'Zulässiges Gesamtgewicht',
            'payload_kg' => 'Nutzlast',
            'has_air_suspension' => 'Luftfederung',
            'has_camera' => 'Kamera im Pferdeabteil',
            'has_climate_control' => 'Klimaanlage',
            'is_active' => 'Aktiv',
            'sort_order' => 'Reihenfolge',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'vehicle_type' => 'Anhänger haben keinen Motor und keinen Kraftstoffverbrauch — sie werden in Angeboten mit einem Zugfahrzeug (Truck) kombiniert.',
        ],
        'placeholder' => [
            'name' => 'z. B. Volvo FH16 — Großtransporter',
        ],
        'suffix' => [
            'horses' => 'Pferde',
        ],
    ],

    'table' => [
        'column' => [
            'vehicle_type' => 'Typ',
            'name' => 'Name',
            'registration_plate' => 'Kennz.',
            'capacity_horses' => 'Pferde',
            'gross_weight_kg' => 'zGG',
            'is_active' => 'Aktiv',
        ],
    ],
];
