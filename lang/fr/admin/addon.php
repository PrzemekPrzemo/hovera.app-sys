<?php

declare(strict_types=1);

return [
    'form' => [
        'helper' => [
            'code' => 'Identifiant (unique au sein du plan), par exemple horses_plus_10.',
            'name' => 'Libellé marketing, par exemple « +10 chevaux ».',
            'resource_type' => 'Type de limite / ressource que l’option augmente.',
            'quantity' => 'Augmentation de la limite (par exemple 10 pour « +10 chevaux »).',
            'sort_order' => 'Plus la valeur est basse, plus l’élément est haut dans la liste.',
        ],
        'label' => [
            'resource_type' => 'Type de ressource',
            'quantity' => 'Quantité',
            'price_monthly' => 'Prix mensuel',
            'price_yearly' => 'Prix annuel',
            'is_active' => 'Actif',
        ],
        'resource_types' => [
            'horses' => 'Chevaux',
            'users' => 'Utilisateurs',
            'clients' => 'Clients',
            'storage_gb' => 'Stockage (Go)',
            'custom' => 'Autre',
        ],
    ],
    'table' => [
        'column' => [
            'resource_type' => 'Ressource',
            'quantity' => 'Qté',
            'price_monthly_short' => 'Mens.',
            'price_yearly' => 'Annuel',
            'is_active_short' => 'Act.',
        ],
        'resource_types_short' => [
            'horses' => 'Chevaux',
            'users' => 'Utilisateurs',
            'clients' => 'Clients',
            'storage_gb' => 'Go',
            'custom' => 'Autre',
        ],
    ],
];
