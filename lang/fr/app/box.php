<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Box intérieur',
        'paddock' => 'Paddock',
        'outdoor' => 'Box extérieur',
        'quarantine' => 'Quarantaine',
    ],
    'types_short' => [
        'indoor' => 'Intérieur',
        'paddock' => 'Paddock',
        'outdoor' => 'Extérieur',
        'quarantine' => 'Quarantaine',
    ],

    'form' => [
        'section' => [
            'box' => 'Box',
            'notes' => 'Notes',
        ],
        'label' => [
            'building' => 'Bâtiment',
            'building_placeholder' => '— sans bâtiment —',
            'name' => 'Nom / numéro',
            'label_short' => 'Code court (par exemple « 12 »)',
            'type' => 'Type',
            'size_m2' => 'Surface (m²)',
            'capacity' => 'Capacité',
            'monthly_rate' => 'Tarif de pension mensuel',
            'is_active' => 'Actif',
            'sort_order' => 'Ordre',
            'notes' => 'Notes',
        ],
        'helper' => [
            'capacity' => 'Nombre de chevaux pouvant occuper ce box (généralement 1 ; les grands boxes collectifs peuvent en accueillir plus).',
            'monthly_rate' => 'Tarif par défaut — peut être surchargé par cheval ou par client.',
        ],
    ],

    'table' => [
        'column' => [
            'building' => 'Bâtiment',
            'building_none' => '— sans bâtiment —',
            'name' => 'Nom',
            'type' => 'Type',
            'size_m2' => 'm²',
            'status' => 'Statut',
            'horse_sex' => 'Sexe du cheval',
            'monthly_rate' => 'Pension',
            'is_active' => 'Actif',
        ],
        'status' => [
            'free' => 'Libre',
            'occupied' => 'Occupé',
        ],
        'filter' => [
            'building' => 'Bâtiment',
            'vacant' => 'Uniquement libres',
            'only_active' => 'Uniquement actifs',
        ],
    ],
];
