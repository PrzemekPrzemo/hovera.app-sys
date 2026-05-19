<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Идентификация',
            'location' => 'Местоположение',
            'subscription' => 'Подписка',
            'branding' => 'Брендинг',
            'branding_description' => 'Используется на публичной странице /s/{slug} и в письмах.',
            'public_profile' => 'Публичный профиль',
            'public_profile_description' => 'Данные, отображаемые на публичной странице конюшни /s/{slug}.',
            'database' => 'База данных',
        ],
        'label' => [
            'type' => 'Тип арендатора',
            'tax_id' => 'NIP / VAT ID',
            'plan' => 'Тариф',
            'primary_color' => 'Основной цвет',
            'logo_url' => 'URL логотипа',
            'public_description' => 'Описание конюшни',
            'public_email' => 'Контактный email (публичный)',
            'public_phone' => 'Контактный телефон',
            'public_address' => 'Адрес',
            'public_website' => 'Сайт',
        ],
        'option' => [
            'type' => [
                'stable' => 'Конная конюшня',
                'transporter' => 'Транспортная компания',
            ],
        ],
        'helper' => [
            'slug' => 'Неизменяемое. Используется в адресах и имени базы.',
            'type' => 'Определяет панель после входа (Конюшня → /app, Транспорт → /transport) и список доступных тарифов. После создания неизменяемо.',
            'plan' => 'Список тарифов фильтруется выбранным типом арендатора.',
        ],
    ],

    'notify' => [
        'created_stable' => 'Конюшня создана',
        'created_transporter' => 'Транспортная компания создана',
        'created_body' => 'База данных :db была инициализирована.',
    ],

    'table' => [
        'column' => [
            'type' => 'Тип',
            'country' => 'Страна',
            'plan' => 'Тариф',
            'db_name' => 'База',
            'created_at' => 'Создана',
        ],
        'filter' => [
            'type' => 'Тип арендатора',
        ],
    ],

    'action' => [
        'suspend' => [
            'label' => 'Приостановить',
            'notification_title' => 'Конюшня приостановлена',
        ],
        'reactivate' => [
            'label' => 'Активировать заново',
            'notification_title' => 'Конюшня снова активна',
        ],
        'soft_delete' => [
            'label' => 'Soft delete',
        ],
        'login_as_owner' => [
            'label' => 'Войти как конюшня',
            'reason_label' => 'Причина имперсонации (аудит GDPR)',
            'reason_helper' => 'Обязательно. Сессия записывается в impersonation_sessions + audit_log_master.',
            'submit' => 'Начать имперсонацию',
            'no_user_title' => 'Нет активного пользователя для этой конюшни',
            'no_user_body' => 'Сначала добавьте участника команды или пригласите владельца.',
        ],
        'seed_demo' => [
            'label' => 'Загрузить демо-данные',
            'modal_heading' => 'Загрузить демо-данные в :name?',
            'modal_description' => 'Добавит 14 лошадей, 6 клиентов, 12 денников, календарь, счета и остальной демонстрационный набор. Работает с базой tenant.',
            'fresh_label' => 'Очистить существующие данные (DROP all tables)',
            'fresh_helper' => 'ВНИМАНИЕ: удалит все текущие данные конюшни перед seed.',
            'success_title' => 'Демо-данные загружены',
            'success_body' => 'Конюшня :name теперь имеет полный демонстрационный набор.',
            'failure_title' => 'Не удалось загрузить демо',
        ],
        'destroy' => [
            'label' => 'Drop database',
            'modal_heading' => 'Окончательно удалить конюшню',
            'modal_description' => 'Эту операцию НЕЛЬЗЯ отменить. База :db и аккаунт MySQL :user будут физически удалены.',
            'confirm_slug_label' => 'Введите slug конюшни для подтверждения',
            'slug_mismatch' => 'Slug не совпадает.',
            'success_title' => 'Конюшня окончательно удалена',
        ],
    ],
];
