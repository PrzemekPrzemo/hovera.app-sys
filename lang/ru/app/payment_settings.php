<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'default_provider' => 'Провайдер по умолчанию',
            'default_provider_description' => 'Выберите, через какой шлюз клиенты должны платить онлайн. «Нет» = всё офлайн (банковский перевод / наличные).',
            'p24' => 'Przelewy24',
            'payu' => 'PayU',
            'stripe' => 'Stripe',
            'mollie' => 'Mollie',
        ],
        'label' => [
            'default_provider' => 'Шлюз по умолчанию',

            // P24
            'p24_merchant_id' => 'Merchant ID',
            'p24_pos_id' => 'POS ID',
            'p24_crc_key' => 'CRC key',
            'p24_api_key' => 'API key (REST)',
            'p24_api_key_helper' => 'Панель P24 → Моя фирма → Точки продаж → Конфигурация → REST API.',
            'p24_sandbox' => 'Sandbox (тест)',
            'p24_force_method' => 'Принудительно выбрать один метод (опционально)',
            'p24_force_method_helper' => 'Пусто = клиент увидит полный список методов в P24 (рекомендуется). Выберите один метод, чтобы сразу направить, напр., в BLIK.',
            'force_method_placeholder' => '— Полный список методов —',

            // PayU
            'payu_pos_id' => 'POS ID (merchantPosId)',
            'payu_client_id' => 'OAuth client_id',
            'payu_client_secret' => 'OAuth client_secret',
            'payu_md5_key' => 'Второй ключ (MD5)',
            'payu_md5_key_helper' => 'Панель PayU → Точки оплаты → Ваш POS → «второй ключ (MD5)».',
            'payu_sandbox' => 'Sandbox (тест)',
            'payu_force_method' => 'Принудительно выбрать один метод (опционально)',
            'payu_force_method_helper' => 'Пусто = клиент увидит полный список методов в PayU (рекомендуется). Выберите напр. BLIK, чтобы сразу направить к этому методу.',

            // Stripe
            'stripe_publishable_key' => 'Publishable key (pk_...)',
            'stripe_secret_key' => 'Secret key (sk_...)',
            'stripe_webhook_secret' => 'Webhook secret (whsec_...)',
            'stripe_webhook_secret_helper' => 'Скопируйте из Stripe Dashboard → Developers → Webhooks → endpoint → Signing secret.',
            'stripe_enabled_methods' => 'Отображаемые методы оплаты',
            'stripe_enabled_methods_helper' => 'Выберите, какие опции клиент увидит в Stripe Checkout. По умолчанию только карты.',

            // Mollie
            'mollie_api_key' => 'API key (live_... или test_...)',
            'mollie_api_key_helper' => 'Получите в Mollie Dashboard → Developers → API keys.',
            'mollie_enabled_methods' => 'Отображаемые методы оплаты',
            'mollie_enabled_methods_helper' => 'Пустой список = Mollie покажет все методы, активные в вашем аккаунте. Один метод = клиент сразу попадёт в этот метод (напр. сразу BLIK).',
        ],
    ],

    'action' => [
        'saved' => 'Настройки платежей сохранены',
    ],
];
