<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'default_provider' => 'Domyślny dostawca',
            'default_provider_description' => 'Wybierz, przez którą bramkę klienci mają płacić online. "Brak" = wszystko offline (przelew tradycyjny / gotówka).',
            'p24' => 'Przelewy24',
            'payu' => 'PayU',
            'stripe' => 'Stripe',
            'mollie' => 'Mollie',
        ],
        'label' => [
            'default_provider' => 'Domyślna bramka',

            // P24
            'p24_merchant_id' => 'Merchant ID',
            'p24_pos_id' => 'POS ID',
            'p24_crc_key' => 'CRC key',
            'p24_api_key' => 'API key (REST)',
            'p24_api_key_helper' => 'Panel P24 → Moja firma → Punkty sprzedaży → Konfiguracja → REST API.',
            'p24_sandbox' => 'Sandbox (test)',
            'p24_force_method' => 'Wymuś jedną metodę (opcjonalne)',
            'p24_force_method_helper' => 'Pusto = klient zobaczy pełną listę metod w P24 (rekomendowane). Wybierz jedną metodę żeby od razu skierować do np. BLIK.',
            'force_method_placeholder' => '— Pełna lista metod —',

            // PayU
            'payu_pos_id' => 'POS ID (merchantPosId)',
            'payu_client_id' => 'OAuth client_id',
            'payu_client_secret' => 'OAuth client_secret',
            'payu_md5_key' => 'Klucz drugi (MD5)',
            'payu_md5_key_helper' => 'Panel PayU → Punkty płatności → Twój POS → "drugi klucz (MD5)".',
            'payu_sandbox' => 'Sandbox (test)',
            'payu_force_method' => 'Wymuś jedną metodę (opcjonalne)',
            'payu_force_method_helper' => 'Pusto = klient zobaczy pełną listę metod w PayU (rekomendowane). Wybierz np. BLIK żeby od razu skierować do tej metody.',

            // Stripe
            'stripe_publishable_key' => 'Publishable key (pk_...)',
            'stripe_secret_key' => 'Secret key (sk_...)',
            'stripe_webhook_secret' => 'Webhook secret (whsec_...)',
            'stripe_webhook_secret_helper' => 'Skopiuj ze Stripe Dashboard → Developers → Webhooks → endpoint → Signing secret.',
            'stripe_enabled_methods' => 'Pokazywane metody płatności',
            'stripe_enabled_methods_helper' => 'Wybierz, które opcje klient zobaczy w Stripe Checkout. Domyślnie tylko karty.',

            // Mollie
            'mollie_api_key' => 'API key (live_... lub test_...)',
            'mollie_api_key_helper' => 'Pobierz z Mollie Dashboard → Developers → API keys.',
            'mollie_enabled_methods' => 'Pokazywane metody płatności',
            'mollie_enabled_methods_helper' => 'Pusta lista = Mollie pokaże wszystkie metody aktywne na Twoim koncie. Pojedyncza metoda = klient idzie od razu do tej metody (np. od razu BLIK).',
        ],
    ],

    'action' => [
        'saved' => 'Zapisano ustawienia płatności',
    ],
];
