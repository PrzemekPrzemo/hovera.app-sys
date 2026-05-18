<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'pricing' => 'Cennik',
            'stripe' => 'Stripe Price IDs',
            'stripe_description' => 'ID cen ze Stripe Dashboard (Products → Pricing). Wymagane do Stripe Checkout subskrypcji. Bez nich klient nie może zapłacić.',
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
            'stripe_price_monthly_id' => 'Skopiuj z Stripe Dashboard → Products → konkretny plan → Pricing → kliknij price ID.',
            'stripe_price_yearly_id' => 'Drugi Price ID dla wariantu rocznego (zwykle Recurring → Yearly).',
            'onboarding_fee' => 'Jednorazowa opłata wdrożeniowa doliczana przy pierwszym checkout. Wymagana dla każdego płatnego planu (Free zostaw puste lub 0).',
            'limits' => 'Standardowe klucze: max_horses, max_clients, max_users, max_storage_mb. -1 = bez limitu.',
            'features' => 'Klucze: bullets[N]=string (marketing), enabled.X=bool (feature flag).',
            'is_active' => 'Czy plan można nadal przypisać do nowych tenantów.',
            'is_public' => 'Czy pokazać na publicznej stronie cennika. Enterprise zwykle false (custom).',
            'audience' => 'Dla kogo jest ten plan — Stajnia lub Firma transportowa. Niezmienne po utworzeniu.',
        ],
        'label' => [
            'audience' => 'Audience',
            'price_monthly' => 'Cena miesięczna',
            'price_yearly' => 'Cena roczna',
            'stripe_price_monthly_id' => 'Stripe Price ID (miesięcznie)',
            'stripe_price_yearly_id' => 'Stripe Price ID (rocznie)',
            'onboarding_fee' => 'Opłata wdrożeniowa (jednorazowa)',
            'is_active' => 'Aktywny',
            'is_public' => 'Publiczny w cenniku',
            'kv_key' => 'Klucz',
            'kv_value' => 'Wartość',
        ],
    ],

    'table' => [
        'column' => [
            'audience' => 'Audience',
            'price_monthly' => 'Miesięcznie',
            'price_yearly' => 'Rocznie',
            'tenants_count' => 'Stajnie',
            'is_active_short' => 'Akt.',
            'is_public_short' => 'Publ.',
        ],
        'filter' => [
            'audience' => 'Audience',
        ],
    ],

    'action' => [
        'delete_blocked_title' => 'Nie można usunąć — plan jest używany.',
        'delete_blocked_body' => ':count stajni jest na tym planie. Najpierw przypisz inny plan.',
    ],
];
