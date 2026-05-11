<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'item' => 'Article de fourrage',
        ],
        'label' => [
            'name' => 'Nom',
            'unit' => 'Unité',
            'low_stock_threshold' => 'Seuil d’alerte',
            'sort_order' => 'Ordre',
            'is_active' => 'Actif',
            'notes' => 'Notes',
            'kind' => 'Type de mouvement',
            'amount' => 'Quantité (positive)',
            'movement_date' => 'Date',
            'movement_notes' => 'Notes du mouvement',
        ],
        'helper' => [
            'low_stock_threshold' => 'Un stock en dessous de ce seuil déclenche une alerte. Vide = pas d’alerte.',
            'amount' => 'Saisissez une valeur positive — le sens dépend du type de mouvement.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Nom',
            'current_stock' => 'Stock',
            'low_stock_threshold' => 'Seuil',
            'is_active' => 'Actif',
            'updated_at' => 'Dernier mouvement',
        ],
        'filter' => [
            'low_stock' => 'Avec seuil d’alerte',
        ],
    ],
    'actions' => [
        'add_movement' => '+ Mouvement de stock',
    ],
    'kind' => [
        'purchase' => 'Réception / livraison',
        'consumption' => 'Sortie / consommation',
        'adjustment' => 'Ajustement d’inventaire',
        'waste' => 'Rebut / destruction',
    ],
    'movements' => [
        'heading' => 'Historique des mouvements',
        'col_date' => 'Date',
        'col_kind' => 'Type',
        'col_amount' => 'Variation',
        'col_notes' => 'Notes',
    ],
];
