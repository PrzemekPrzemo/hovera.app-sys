<?php

declare(strict_types=1);

return [
    'navigation' => 'Przelewy24',
    'title' => 'Konfiguracja Przelewy24',

    'section' => [
        'account' => 'Konto Przelewy24',
        'account_help' => 'Dane sklepu z panelu Przelewy24 (panel.przelewy24.pl → Moje sklepy → Dane sklepu).',
        'secrets' => 'Klucze API',
        'secrets_help' => 'Klucze są szyfrowane (Laravel Crypt) i nigdy nie są wyświetlane plaintekstem po zapisie. Aby zmienić — wpisz nową wartość, puste pole nie nadpisuje.',
        'webhook' => 'URL-e do skonfigurowania w panelu P24',
        'webhook_help' => 'Wklej te URL-e w panel Przelewy24 → Moje sklepy → Konfiguracja → Ustawienia powiadomień / Adres URL powrotu.',
    ],

    'field' => [
        'env' => 'Środowisko',
        'merchant_id' => 'ID merchanta',
        'merchant_id_help' => '6-cyfrowy numer z panelu P24 (np. 168172).',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Najczęściej taki sam jak merchant ID.',
        'api_key' => 'Klucz API (sekret)',
        'api_key_help' => 'Klucz raportów — panel P24 → Moje sklepy → Konfiguracja → Klucze.',
        'api_key_status' => 'Status klucza API',
        'crc' => 'Klucz CRC (sekret)',
        'crc_help' => 'Klucz do podpisu transakcji — panel P24 → Moje sklepy → Konfiguracja → Klucze → CRC.',
        'crc_status' => 'Status klucza CRC',
        'webhook_url' => 'Webhook (powiadomienia o statusie)',
        'return_url' => 'URL powrotu po płatności',
    ],

    'env' => [
        'sandbox' => 'Sandbox (testowe)',
        'production' => 'Produkcja',
    ],

    'status' => [
        'configured' => 'Skonfigurowane',
        'not_configured' => 'Nieskonfigurowane',
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację',
        'saved' => 'Konfiguracja Przelewy24 zapisana.',
    ],
];
