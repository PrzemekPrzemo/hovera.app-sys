<?php

declare(strict_types=1);

return [
    'navigation' => 'Przelewy24',
    'title' => 'Конфигурация Przelewy24',

    'section' => [
        'account' => 'Аккаунт Przelewy24',
        'account_help' => 'Данные магазина из панели Przelewy24 (panel.przelewy24.pl → Мои магазины → Данные магазина).',
        'secrets' => 'API-ключи',
        'secrets_help' => 'Ключи шифруются (Laravel Crypt) и никогда не отображаются в открытом виде после сохранения. Чтобы изменить — введите новое значение, пустое поле не перезаписывает.',
        'webhook' => 'URL для настройки в панели P24',
        'webhook_help' => 'Вставьте эти URL в панель Przelewy24 → Мои магазины → Конфигурация → Настройки уведомлений / URL возврата.',
    ],

    'field' => [
        'env' => 'Окружение',
        'merchant_id' => 'ID merchant',
        'merchant_id_help' => '6-значный номер из панели P24 (напр. 168172).',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Чаще всего совпадает с merchant ID.',
        'api_key' => 'API-ключ (секрет)',
        'api_key_help' => 'Ключ отчётов — панель P24 → Мои магазины → Конфигурация → Ключи.',
        'api_key_status' => 'Статус API-ключа',
        'crc' => 'CRC-ключ (секрет)',
        'crc_help' => 'Ключ для подписи транзакций — панель P24 → Мои магазины → Конфигурация → Ключи → CRC.',
        'crc_status' => 'Статус CRC-ключа',
        'webhook_url' => 'Webhook (уведомления о статусе)',
        'return_url' => 'URL возврата после оплаты',
    ],

    'env' => [
        'sandbox' => 'Sandbox (тестовое)',
        'production' => 'Прод',
    ],

    'status' => [
        'configured' => 'Настроено',
        'not_configured' => 'Не настроено',
    ],

    'action' => [
        'save_button' => 'Сохранить конфигурацию',
        'saved' => 'Конфигурация Przelewy24 сохранена.',
    ],
];
