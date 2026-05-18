<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Идентификация',
            'pricing' => 'Цены',
            'stripe' => 'Stripe Price IDs',
            'stripe_description' => 'ID цен из Stripe Dashboard (Products → Pricing). Необходимы для Stripe Checkout подписок. Без них клиент не сможет оплатить.',
            'limits' => 'Лимиты',
            'limits_description' => 'Жёсткие лимиты тарифа — применяются в приложении (CreateTenant блокирует при превышении лимита плана).',
            'features' => 'Функционал',
            'features_description' => 'Список маркетинговых пунктов + флаги функций для системы feature-flag.',
            'visibility' => 'Видимость',
        ],
        'helper' => [
            'code' => 'Уникальный идентификатор (напр. free, stable, pro). Используется в API + ссылках.',
            'sort_order' => 'Меньше = выше в списке.',
            'price_yearly' => 'Обычно 10× месячной минус 10–30% годовой скидки.',
            'stripe_price_monthly_id' => 'Скопируйте из Stripe Dashboard → Products → конкретный тариф → Pricing → нажмите price ID.',
            'stripe_price_yearly_id' => 'Второй Price ID для годового варианта (обычно Recurring → Yearly).',
            'onboarding_fee' => 'Единоразовая плата за внедрение, добавляемая при первом checkout. Обязательна для каждого платного тарифа (Free оставьте пустым или 0).',
            'limits' => 'Стандартные ключи: max_horses, max_clients, max_users, max_storage_mb. -1 = без лимита.',
            'features' => 'Ключи: bullets[N]=string (маркетинг), enabled.X=bool (feature flag).',
            'is_active' => 'Можно ли тариф ещё назначать новым tenants.',
            'is_public' => 'Показывать ли на публичной странице прайс-листа. Enterprise обычно false (custom).',
            'audience' => 'Для кого этот тариф — Конюшня или Транспортная компания. После создания не меняется.',
        ],
        'label' => [
            'audience' => 'Audience',
            'price_monthly' => 'Месячная цена',
            'price_yearly' => 'Годовая цена',
            'stripe_price_monthly_id' => 'Stripe Price ID (месячно)',
            'stripe_price_yearly_id' => 'Stripe Price ID (годовой)',
            'onboarding_fee' => 'Плата за внедрение (единоразово)',
            'is_active' => 'Активен',
            'is_public' => 'Публичный в прайс-листе',
            'kv_key' => 'Ключ',
            'kv_value' => 'Значение',
        ],
    ],

    'table' => [
        'column' => [
            'audience' => 'Audience',
            'price_monthly' => 'Месячно',
            'price_yearly' => 'Годовой',
            'tenants_count' => 'Конюшни',
            'is_active_short' => 'Акт.',
            'is_public_short' => 'Публ.',
        ],
        'filter' => [
            'audience' => 'Audience',
        ],
    ],

    'action' => [
        'delete_blocked_title' => 'Невозможно удалить — тариф используется.',
        'delete_blocked_body' => ':count конюшен на этом тарифе. Сначала назначьте другой тариф.',
    ],
];
