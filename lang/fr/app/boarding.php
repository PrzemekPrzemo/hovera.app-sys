<?php

declare(strict_types=1);

return [
    'vat_rates' => [
        '23' => '23 %',
        '8' => '8 %',
        '5' => '5 %',
        '0' => '0 %',
        'zw' => 'exonéré',
        'np' => 'non applicable',
    ],

    'form' => [
        'section' => [
            'service' => 'Prestation du tarif',
            'service_description' => 'Vous sélectionnez ces prestations pour chaque cheval (onglet « Pension » sur la fiche du cheval). Elles apparaîtront dans le portail client — le propriétaire voit ce qu’il paie.',
        ],
        'label' => [
            'name' => 'Nom',
            'name_placeholder' => 'par exemple Foin, Nettoyage du box, Transport en concours',
            'description' => 'Description (optionnelle)',
            'unit' => 'Unité',
            'unit_placeholder' => 'pcs / kg / h / mois',
            'frequency' => 'Fréquence de facturation',
            'price_net' => 'Prix HT',
            'vat_rate' => 'Taux de TVA',
            'is_active' => 'Active',
            'sort_order' => 'Ordre',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom',
            'frequency' => 'Fréquence',
            'price_net' => 'Prix HT',
            'vat' => 'TVA',
            'horses_count' => 'Chevaux',
            'is_active' => 'Active',
        ],
    ],
];
