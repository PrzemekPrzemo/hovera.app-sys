<?php

declare(strict_types=1);

return [
    'section' => [
        'identification' => 'Identyfikacja',
        'capacity' => 'Pojemność i masa',
        'equipment' => 'Wyposażenie',
        'other' => 'Pozostałe',
    ],

    'form' => [
        'label' => [
            'name' => 'Nazwa pojazdu',
            'registration_plate' => 'Numer rejestracyjny',
            'year_of_manufacture' => 'Rok produkcji',
            'capacity_horses' => 'Pojemność (koni)',
            'gross_weight_kg' => 'DMC',
            'payload_kg' => 'Ładowność',
            'has_air_suspension' => 'Zawieszenie pneumatyczne',
            'has_camera' => 'Kamera w przedziale dla koni',
            'has_climate_control' => 'Klimatyzacja przedziału',
            'is_active' => 'Aktywny',
            'sort_order' => 'Kolejność',
            'notes' => 'Notatki',
        ],
        'placeholder' => [
            'name' => 'np. Volvo FH16 — wóz duży',
        ],
        'suffix' => [
            'horses' => 'koni',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'registration_plate' => 'Nr rej.',
            'capacity_horses' => 'Koni',
            'gross_weight_kg' => 'DMC',
            'is_active' => 'Aktywny',
        ],
    ],
];
