<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'pricing' => 'Cennik',
            'limits' => 'Limity',
            'limits_description' => 'Twarde limity planu — egzekwowane w aplikacji (CreateTenant blokuje gdy plan przekroczony).',
            'features' => 'Funkcjonalności',
            'features_description' => 'Lista marketingowych bullet pointów + flag fukcjonalnych dla feature-flag system.',
            'visibility' => 'Widoczność',
        ],
        'helper' => [
            'code' => 'Unikalny identyfikator (np. free, stable, pro). Używany w API + linkach.',
            'sort_order' => 'Niższe = wyżej na liście.',
            'price_yearly' => 'Zwykle 10× miesięczna minus 10-30% zniżki rocznej.',
            'limits' => 'Standardowe klucze: max_horses, max_clients, max_users, max_storage_mb. -1 = bez limitu.',
            'features' => 'Klucze: bullets[N]=string (marketing), enabled.X=bool (feature flag).',
            'is_active' => 'Czy plan można nadal przypisać do nowych tenantów.',
            'is_public' => 'Czy pokazać na publicznej stronie cennika. Enterprise zwykle false (custom).',
        ],
        'label' => [
            'price_monthly' => 'Cena miesięczna',
            'price_yearly' => 'Cena roczna',
            'is_active' => 'Aktywny',
            'is_public' => 'Publiczny w cenniku',
            'kv_key' => 'Klucz',
            'kv_value' => 'Wartość',
        ],
    ],

    'table' => [
        'column' => [
            'price_monthly' => 'Miesięcznie',
            'price_yearly' => 'Rocznie',
            'tenants_count' => 'Stajnie',
            'is_active_short' => 'Akt.',
            'is_public_short' => 'Publ.',
        ],
    ],

    'action' => [
        'delete_blocked_title' => 'Nie można usunąć — plan jest używany.',
        'delete_blocked_body' => ':count stajni jest na tym planie. Najpierw przypisz inny plan.',
    ],
];
