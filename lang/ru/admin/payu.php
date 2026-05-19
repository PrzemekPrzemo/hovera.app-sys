<?php

declare(strict_types=1);

return [
    'navigation' => 'PayU',
    'title' => 'Конфигурация PayU',

    'section' => [
        'account' => 'Аккаунт PayU',
        'account_help' => 'Данные точки продаж из панели PayU (panel.payu.com → Мой магазин → Конфигурация → Точки продаж).',
        'secrets' => 'API-ключи',
        'secrets_help' => 'Ключи шифруются (Laravel Crypt) и никогда не показываются в открытом виде после сохранения. Для изменения — введите новое значение, пустое поле не перезаписывает.',
        'webhook' => 'URL-адреса для настройки в панели PayU',
        'webhook_help' => 'Вставьте URL вебхука в PayU → Точки продаж → Конфигурация → URL уведомлений (notifyUrl).',
    ],

    'field' => [
        'env' => 'Среда',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Номер точки продаж (merchantPosId) — панель PayU → Мой магазин → Точки продаж.',
        'oauth_client_id' => 'OAuth Client ID',
        'oauth_client_id_help' => 'Идентификатор OAuth-клиента для авторизации REST API — PayU → Точки продаж → Конфигурация → Протокол REST API.',
        'oauth_client_secret' => 'OAuth Client Secret',
        'oauth_client_secret_help' => 'Секрет OAuth — обменивается на access_token через grant_type=client_credentials.',
        'oauth_client_secret_status' => 'Статус OAuth Client Secret',
        'md5_key' => 'Второй ключ (MD5)',
        'md5_key_help' => 'Ключ для проверки подписи вебхука (заголовок OpenPayU-Signature). Панель PayU → Конфигурационные ключи.',
        'md5_key_status' => 'Статус ключа MD5',
        'second_key' => 'Второй ключ',
        'second_key_help' => 'Опциональный ключ для проверки status callback в устаревшем формовом потоке. Большинству интеграций не нужен — оставьте пустым.',
        'second_key_status' => 'Статус второго ключа',
        'webhook_url' => 'URL вебхука (уведомления о статусе)',
        'return_url' => 'URL возврата после оплаты',
    ],

    'env' => [
        'sandbox' => 'Песочница (secure.snd.payu.com)',
        'production' => 'Продакшен (secure.payu.com)',
    ],

    'status' => [
        'configured' => 'Настроено',
        'not_configured' => 'Не настроено',
    ],

    'action' => [
        'save_button' => 'Сохранить конфигурацию',
        'saved' => 'Конфигурация PayU сохранена.',
    ],
];
