<?php

declare(strict_types=1);

return [
    'tokens' => [
        'navigation' => 'Мои API-токены',
        'title' => 'Личные API-токены мастер-администратора',
        'col' => [
            'name' => 'Название',
            'abilities' => 'Права',
            'last_used_at' => 'Последнее использование',
            'created_at' => 'Создан',
            'expires_at' => 'Истекает',
            'never' => 'никогда',
        ],
        'action' => [
            'generate' => 'Создать токен',
            'generate_submit' => 'Сгенерировать',
            'revoke' => 'Отозвать',
            'revoke_confirm' => 'Токен перестанет работать немедленно — все скрипты с этим токеном получат 401.',
            'revoke_success' => 'Токен отозван',
        ],
        'form' => [
            'name' => 'Название токена',
            'name_placeholder' => 'напр. Monitoring script',
            'abilities' => 'Права (scopes)',
            'abilities_help' => 'Выберите минимум, необходимый для работы. "admin-all" даёт полный доступ.',
            'expiry' => 'Срок действия',
            'expiry_none' => 'Без срока действия',
            'expiry_30d' => '30 дней',
            'expiry_90d' => '90 дней',
            'expiry_1y' => '1 год',
        ],
        'abilities' => [
            'read-tenants' => 'Чтение конюшен (read-tenants)',
            'read-billing' => 'Чтение биллинга/Stripe (read-billing)',
            'read-system' => 'Чтение системных метрик (read-system)',
            'admin-impersonate' => 'Имперсонация пользователей (admin-impersonate)',
            'admin-all' => 'Полный доступ администратора (admin-all)',
        ],
        'modal' => [
            'heading' => 'Токен создан',
            'warning' => 'Скопируйте сейчас — вы больше его не увидите. Если потеряете, создайте новый.',
            'name_label' => 'Токен',
            'copy' => 'Копировать в буфер обмена',
        ],
    ],

    'tenant_tokens' => [
        'navigation' => 'API-токены конюшен',
        'title' => 'API-токены, выданные конюшням',
        'col' => [
            'user' => 'Пользователь',
            'tenant' => 'Конюшня',
            'name' => 'Название токена',
            'abilities' => 'Права',
            'last_used_at' => 'Последнее использование',
            'created_at' => 'Создан',
            'ip' => 'IP',
            'user_agent' => 'User-Agent',
        ],
        'filter' => [
            'tenant' => 'Конюшня',
            'activity' => 'Активность',
            'active_30d' => 'Активные (30 дней)',
            'dormant' => 'Неактивные (без активности)',
            'any' => 'Любые',
            'created_range' => 'Диапазон создания',
        ],
        'action' => [
            'revoke' => 'Отозвать',
            'revoke_confirm' => 'Токен перестанет работать немедленно. Мобильное приложение этого пользователя должно будет войти заново.',
            'revoke_success' => 'Токен отозван',
        ],
        'bulk' => [
            'revoke' => 'Отозвать выбранные',
            'revoked' => 'Отозвано :count токенов',
        ],
    ],

    'webhooks' => [
        'navigation' => 'Webhooks конюшен',
        'model' => 'Подписка на webhook',
        'model_plural' => 'Webhooks',
        'col' => [
            'tenant' => 'Конюшня',
            'url_host' => 'Хост URL',
            'events' => 'Событий',
            'is_active' => 'Активен',
            'last_delivery' => 'Последняя доставка',
            'last_delivery_at' => 'Время последней доставки',
            'created_at' => 'Создан',
        ],
        'form' => [
            'section' => [
                'target' => 'Endpoint и события',
                'signing' => 'Подпись запросов',
            ],
            'tenant' => 'Конюшня',
            'is_active' => 'Активен',
            'url' => 'URL endpoint',
            'url_help' => 'POST на этот URL при возникновении одного из выбранных событий. Рекомендуется HTTPS.',
            'events' => 'События (events)',
            'secret' => 'Секрет HMAC',
            'secret_regenerated' => 'Создан новый секрет',
            'signing_help' => 'Каждый запрос содержит заголовок X-Hovera-Signature: sha256=<hex>, рассчитанный HMAC по body. Получатель должен проверить подпись тем же секретом.',
        ],
        'filter' => [
            'tenant' => 'Конюшня',
            'is_active' => 'Активные',
        ],
        'action' => [
            'enable' => 'Включить',
            'disable' => 'Выключить',
            'toggled' => 'Состояние изменено',
        ],
        'deliveries' => [
            'title' => 'История доставок (последние 50)',
            'col' => [
                'event' => 'Событие',
                'attempt' => 'Попытка',
                'status' => 'Код HTTP',
                'duration' => 'Время',
                'delivered_at' => 'Отправлено',
                'error' => 'Ошибка',
                'payload' => 'Payload',
            ],
            'action' => [
                'resend' => 'Отправить повторно',
                'resent' => 'Повторная доставка поставлена в очередь',
            ],
        ],
    ],
];
