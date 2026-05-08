<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'pass' => 'Karnet',
        ],
        'label' => [
            'client' => 'Klient',
            'name' => 'Nazwa',
            'name_placeholder' => 'Karnet 8 jazd',
            'total_uses' => 'Liczba jazd',
            'remaining_uses' => 'Pozostało',
            'valid_from' => 'Ważny od',
            'valid_until' => 'Ważny do',
            'price' => 'Cena karnetu',
            'cancellation_policy_hours' => 'Polityka odwołania (h)',
            'cancellation_policy_placeholder' => 'użyj domyślnej z ustawień stajni',
            'status' => 'Status',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'remaining_uses' => 'Auto-aktualizowane przez system; ręczna zmiana tylko w wyjątkowych sytuacjach.',
            'cancellation_policy_hours' => 'Odwołanie X godzin przed jazdą = bez kosztu (karnet wraca).',
        ],
    ],

    'table' => [
        'column' => [
            'client' => 'Klient',
            'name' => 'Karnet',
            'remaining_uses' => 'Pozostało',
            'status' => 'Status',
            'valid_until' => 'Ważny do',
            'price' => 'Cena',
            'cancellation_policy' => 'Odwołanie',
            'cancellation_policy_default' => 'wg ustawień stajni',
            'created_at' => 'Wystawiony',
        ],
        'filter' => [
            'client' => 'Klient',
        ],
    ],
];
