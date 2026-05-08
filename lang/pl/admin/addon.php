<?php

declare(strict_types=1);

return [
    'form' => [
        'helper' => [
            'code' => 'Identyfikator (unikalny w obrębie planu), np. horses_plus_10.',
            'name' => 'Etykieta marketingowa, np. "+10 koni".',
            'resource_type' => 'Rodzaj limitu/zasobu który dodatek zwiększa.',
            'quantity' => 'O ile zwiększa limit (np. 10 dla "+10 koni").',
            'sort_order' => 'Niższe = wyżej na liście.',
        ],
        'label' => [
            'resource_type' => 'Typ zasobu',
            'quantity' => 'Ilość',
            'price_monthly' => 'Cena miesięczna',
            'price_yearly' => 'Cena roczna',
            'is_active' => 'Aktywny',
        ],
        'resource_types' => [
            'horses' => 'Konie',
            'users' => 'Użytkownicy',
            'clients' => 'Klienci',
            'storage_gb' => 'Storage (GB)',
            'custom' => 'Inne',
        ],
    ],
    'table' => [
        'column' => [
            'resource_type' => 'Zasób',
            'quantity' => 'Ilość',
            'price_monthly_short' => 'Mies.',
            'price_yearly' => 'Rocznie',
            'is_active_short' => 'Akt.',
        ],
        'resource_types_short' => [
            'horses' => 'Konie',
            'users' => 'Użytkownicy',
            'clients' => 'Klienci',
            'storage_gb' => 'GB',
            'custom' => 'Inne',
        ],
    ],
];
