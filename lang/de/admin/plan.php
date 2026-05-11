<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identifikation',
            'pricing' => 'Preise',
            'stripe' => 'Stripe Price IDs',
            'stripe_description' => 'Price-IDs aus dem Stripe Dashboard (Products → Pricing). Erforderlich für Stripe Checkout-Abonnements. Ohne sie kann der Kunde nicht zahlen.',
            'limits' => 'Limits',
            'limits_description' => 'Harte Tariflimits — werden in der Anwendung durchgesetzt (CreateTenant blockiert, wenn der Tarif überschritten ist).',
            'features' => 'Funktionen',
            'features_description' => 'Liste der Marketing-Bullet-Points + Feature-Flags für das Feature-Flag-System.',
            'visibility' => 'Sichtbarkeit',
        ],
        'helper' => [
            'code' => 'Eindeutige Kennung (z. B. free, stable, pro). Wird in API + Links verwendet.',
            'sort_order' => 'Niedriger = weiter oben in der Liste.',
            'price_yearly' => 'In der Regel 10× monatlich abzüglich 10–30 % Jahresrabatt.',
            'stripe_price_monthly_id' => 'Aus dem Stripe Dashboard kopieren → Products → konkreter Tarif → Pricing → Price-ID anklicken.',
            'stripe_price_yearly_id' => 'Zweite Price-ID für die jährliche Variante (in der Regel Recurring → Yearly).',
            'onboarding_fee' => 'Einmalige Einrichtungsgebühr, die beim ersten Checkout berechnet wird. Pflicht für jeden kostenpflichtigen Tarif (Free leer lassen oder 0).',
            'limits' => 'Standardschlüssel: max_horses, max_clients, max_users, max_storage_mb. -1 = unbegrenzt.',
            'features' => 'Schlüssel: bullets[N]=string (Marketing), enabled.X=bool (Feature-Flag).',
            'is_active' => 'Kann der Tarif noch neuen Tenants zugewiesen werden.',
            'is_public' => 'Auf der öffentlichen Preisseite anzeigen? Enterprise üblicherweise false (custom).',
        ],
        'label' => [
            'price_monthly' => 'Monatspreis',
            'price_yearly' => 'Jahrespreis',
            'stripe_price_monthly_id' => 'Stripe Price ID (monatlich)',
            'stripe_price_yearly_id' => 'Stripe Price ID (jährlich)',
            'onboarding_fee' => 'Einrichtungsgebühr (einmalig)',
            'is_active' => 'Aktiv',
            'is_public' => 'Öffentlich im Preisplan',
            'kv_key' => 'Schlüssel',
            'kv_value' => 'Wert',
        ],
    ],

    'table' => [
        'column' => [
            'price_monthly' => 'Monatlich',
            'price_yearly' => 'Jährlich',
            'tenants_count' => 'Reitställe',
            'is_active_short' => 'Akt.',
            'is_public_short' => 'Öff.',
        ],
    ],

    'action' => [
        'delete_blocked_title' => 'Löschen nicht möglich — Tarif wird verwendet.',
        'delete_blocked_body' => ':count Reitställe nutzen diesen Tarif. Weisen Sie zuerst einen anderen Tarif zu.',
    ],
];
