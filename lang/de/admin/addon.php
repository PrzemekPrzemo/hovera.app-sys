<?php

declare(strict_types=1);

return [
    'form' => [
        'helper' => [
            'code' => 'Kennung (innerhalb des Tarifs eindeutig), z. B. horses_plus_10.',
            'name' => 'Marketing-Bezeichnung, z. B. "+10 Pferde".',
            'resource_type' => 'Art des Limits/der Ressource, die die Zusatzoption erweitert.',
            'quantity' => 'Um wie viel das Limit erhöht wird (z. B. 10 für "+10 Pferde").',
            'sort_order' => 'Niedriger = weiter oben in der Liste.',
        ],
        'label' => [
            'resource_type' => 'Ressourcentyp',
            'quantity' => 'Menge',
            'price_monthly' => 'Monatspreis',
            'price_yearly' => 'Jahrespreis',
            'is_active' => 'Aktiv',
        ],
        'resource_types' => [
            'horses' => 'Pferde',
            'users' => 'Benutzer',
            'clients' => 'Kunden',
            'storage_gb' => 'Speicher (GB)',
            'custom' => 'Sonstige',
        ],
    ],
    'table' => [
        'column' => [
            'resource_type' => 'Ressource',
            'quantity' => 'Menge',
            'price_monthly_short' => 'Mon.',
            'price_yearly' => 'Jährlich',
            'is_active_short' => 'Akt.',
        ],
        'resource_types_short' => [
            'horses' => 'Pferde',
            'users' => 'Benutzer',
            'clients' => 'Kunden',
            'storage_gb' => 'GB',
            'custom' => 'Sonstige',
        ],
    ],
];
