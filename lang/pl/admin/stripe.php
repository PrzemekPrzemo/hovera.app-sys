<?php

declare(strict_types=1);

return [
    'navigation' => 'Stripe',
    'title' => 'Konfiguracja Stripe (subskrypcje SaaS)',

    'section' => [
        'env' => 'Środowisko',
        'env_help' => 'Test = klucze pk_test_/sk_test_ (sandbox). Live = klucze pk_live_/sk_live_ — prawdziwe płatności od klientów.',
        'keys' => 'Klucze API',
        'keys_help' => 'Z panelu Stripe → Developers → API keys. Publishable trafia do JS frontendu; Secret zostaje serwerowo. Klucze szyfrowane (Laravel Crypt) — po zapisie nie są widoczne plaintekstem.',
        'webhook' => 'Webhook',
        'webhook_help' => 'Wklej Webhook URL w panel Stripe → Developers → Webhooks → Add endpoint. Po utworzeniu skopiuj signing secret (whsec_…) tutaj.',
        'events' => 'Eventy do zaznaczenia w panelu Stripe',
        'events_help' => 'Po utworzeniu endpointu w Stripe, zaznacz w "Select events":',
        'plan_prices' => 'Stripe Price IDs per plan',
        'plan_prices_help' => 'Każdy plan (Solo/Stable/Pro) musi mieć Stripe Price ID dla wariantu miesięcznego i rocznego. Stwórz Products + Prices w panelu Stripe, wklej ID-ki na stronie planu.',
        'plan_prices_link' => 'Przejdź do listy planów →',
    ],

    'field' => [
        'env' => 'Tryb',
        'publishable_key' => 'Publishable key (pk_…)',
        'publishable_key_help' => 'Klucz publiczny używany przez frontend Stripe.js / Stripe Checkout.',
        'publishable_key_status' => 'Status klucza publishable',
        'secret_key' => 'Secret key (sk_…)',
        'secret_key_help' => 'Klucz serwerowy — NIGDY nie wystawiaj publicznie. Używany do tworzenia checkout sessions, refundów itd.',
        'secret_key_status' => 'Status klucza secret',
        'webhook_url' => 'Webhook URL',
        'webhook_secret' => 'Webhook signing secret (whsec_…)',
        'webhook_secret_help' => 'Skopiuj z panelu Stripe po utworzeniu webhook endpoint.',
        'webhook_secret_status' => 'Status webhook secret',
    ],

    'env' => [
        'test' => 'Test (sandbox)',
        'live' => 'Live (produkcja)',
    ],

    'status' => [
        'configured' => 'Skonfigurowane',
        'not_configured' => 'Nieskonfigurowane',
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację',
        'saved' => 'Konfiguracja Stripe zapisana.',
    ],
];
