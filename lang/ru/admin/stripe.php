<?php

declare(strict_types=1);

return [
    'navigation' => 'Stripe',
    'title' => 'Конфигурация Stripe (SaaS-подписки)',

    'section' => [
        'env' => 'Окружение',
        'env_help' => 'Test = ключи pk_test_/sk_test_ (sandbox). Live = ключи pk_live_/sk_live_ — настоящие платежи от клиентов.',
        'keys' => 'API-ключи',
        'keys_help' => 'Из панели Stripe → Developers → API keys. Publishable идёт во фронтенд JS; Secret остаётся на сервере. Ключи шифруются (Laravel Crypt) — после сохранения не отображаются в открытом виде.',
        'webhook' => 'Webhook',
        'webhook_help' => 'Вставьте Webhook URL в панель Stripe → Developers → Webhooks → Add endpoint. После создания скопируйте signing secret (whsec_…) сюда.',
        'events' => 'События для выбора в панели Stripe',
        'events_help' => 'После создания endpoint в Stripe отметьте в "Select events":',
        'plan_prices' => 'Stripe Price IDs для каждого тарифа',
        'plan_prices_help' => 'Каждый тариф (Solo/Stable/Pro) должен иметь Stripe Price ID для месячного и годового варианта. Создайте Products + Prices в панели Stripe, вставьте ID на странице тарифа.',
        'plan_prices_link' => 'Перейти к списку тарифов →',
    ],

    'field' => [
        'env' => 'Режим',
        'publishable_key' => 'Publishable key (pk_…)',
        'publishable_key_help' => 'Публичный ключ, используемый фронтендом Stripe.js / Stripe Checkout.',
        'publishable_key_status' => 'Статус publishable-ключа',
        'secret_key' => 'Secret key (sk_…)',
        'secret_key_help' => 'Серверный ключ — НИКОГДА не публикуйте его. Используется для создания checkout sessions, возвратов и т. д.',
        'secret_key_status' => 'Статус secret-ключа',
        'webhook_url' => 'Webhook URL',
        'webhook_secret' => 'Webhook signing secret (whsec_…)',
        'webhook_secret_help' => 'Скопируйте из панели Stripe после создания webhook endpoint.',
        'webhook_secret_status' => 'Статус webhook secret',
    ],

    'env' => [
        'test' => 'Test (sandbox)',
        'live' => 'Live (прод)',
    ],

    'status' => [
        'configured' => 'Настроено',
        'not_configured' => 'Не настроено',
    ],

    'action' => [
        'save_button' => 'Сохранить конфигурацию',
        'saved' => 'Конфигурация Stripe сохранена.',
    ],
];
