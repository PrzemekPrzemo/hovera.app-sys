<?php

declare(strict_types=1);

return [
    'payment_method_label' => 'Stripe (карта / BLIK / Przelewy24)',

    'section' => [
        'title' => 'Stripe Connect Express (онлайн-платежи)',
        'description' => 'Активация в один клик — собственный счёт Stripe Express, деньги поступают напрямую вам. Онлайн-оплата для каждого предложения автоматически.',
        'disclaimer' => 'Stripe Connect Express: ВАШ счёт Stripe, ВАШ договор со Stripe (KYC у Stripe). Hovera только технически обеспечивает checkout — деньги поступают напрямую вам. Hovera может (по умолчанию не взимает) комиссию с транзакций — см. §15 регламента marketplace.',
    ],

    'form' => [
        'label' => [
            'status' => 'Статус интеграции',
        ],
    ],

    'status' => [
        'none' => 'Не подключено',
        'pending' => 'Проверка в Stripe',
        'enabled' => 'Активно — можно принимать платежи',
        'restricted' => 'Ограничено — заполните данные у Stripe',
        'rejected' => 'Отклонено — обратитесь в поддержку Stripe',
    ],

    'action' => [
        'connect' => 'Подключить счёт Stripe',
        'refresh_status' => 'Проверить статус',
        'open_dashboard' => 'Открыть панель Stripe',
        'admin_sync' => 'Синхронизировать статус Stripe',
    ],

    'notify' => [
        'onboard_failed' => 'Не удалось начать onboarding Stripe.',
        'status_sync_failed' => 'Не удалось синхронизировать статус Stripe.',
        'dashboard_failed' => 'Не удалось сгенерировать ссылку на панель Stripe.',
        'refreshed' => 'Статус Stripe обновлён.',
        'status_none' => 'Нет счёта Stripe — нажмите «Подключить счёт Stripe».',
        'status_pending' => 'KYC в процессе — Stripe проверяет данные компании. Попробуйте через некоторое время.',
        'status_enabled' => 'Счёт Stripe активен — можно выставлять предложения с онлайн-оплатой.',
        'status_restricted' => 'Stripe ограничил счёт — проверьте панель и заполните недостающие данные.',
        'status_rejected' => 'Stripe отклонил счёт — требуется обращение в поддержку Stripe.',
    ],
];
