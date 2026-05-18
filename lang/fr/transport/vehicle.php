<?php

declare(strict_types=1);

return [
    'section' => [
        'identification' => 'Identification',
        'capacity' => 'Capacité et poids',
        'equipment' => 'Équipement',
        'other' => 'Autres',
    ],

    'form' => [
        'label' => [
            'name' => 'Nom du véhicule',
            'registration_plate' => 'Plaque d’immatriculation',
            'year_of_manufacture' => 'Année de fabrication',
            'capacity_horses' => 'Capacité (chevaux)',
            'gross_weight_kg' => 'Poids total',
            'payload_kg' => 'Charge utile',
            'has_air_suspension' => 'Suspension pneumatique',
            'has_camera' => 'Caméra dans le compartiment',
            'has_climate_control' => 'Climatisation',
            'is_active' => 'Actif',
            'sort_order' => 'Ordre',
            'notes' => 'Notes',
        ],
        'placeholder' => [
            'name' => 'p. ex. Volvo FH16 — grand camion',
        ],
        'suffix' => [
            'horses' => 'chevaux',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom',
            'registration_plate' => 'Plaque',
            'capacity_horses' => 'Chev.',
            'gross_weight_kg' => 'PTC',
            'is_active' => 'Actif',
        ],
    ],
];
