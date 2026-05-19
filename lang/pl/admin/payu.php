<?php

declare(strict_types=1);

return [
    'navigation' => 'PayU',
    'title' => 'Konfiguracja PayU',

    'section' => [
        'account' => 'Konto PayU',
        'account_help' => 'Dane Punktu Płatności z panelu PayU (panel.payu.com → Mój sklep → Konfiguracja → Punkty płatności).',
        'secrets' => 'Klucze API',
        'secrets_help' => 'Klucze są szyfrowane (Laravel Crypt) i nigdy nie są wyświetlane plaintekstem po zapisie. Aby zmienić — wpisz nową wartość, puste pole nie nadpisuje.',
        'webhook' => 'URL-e do skonfigurowania w panelu PayU',
        'webhook_help' => 'Wklej webhook URL w panel PayU → Punkty płatności → Konfiguracja → Adres powiadomień (notifyUrl).',
    ],

    'field' => [
        'env' => 'Środowisko',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Numer Punktu Płatności (merchantPosId) — panel PayU → Mój sklep → Punkty płatności.',
        'oauth_client_id' => 'OAuth Client ID',
        'oauth_client_id_help' => 'Identyfikator klienta OAuth dla autoryzacji REST API — panel PayU → Punkty płatności → Konfiguracja → Protokół REST API.',
        'oauth_client_secret' => 'OAuth Client Secret',
        'oauth_client_secret_help' => 'Klucz sekretu OAuth — wymiana na access_token przez grant_type=client_credentials.',
        'oauth_client_secret_status' => 'Status OAuth Client Secret',
        'md5_key' => 'Klucz drugi (MD5)',
        'md5_key_help' => 'Klucz do podpisu webhook (header OpenPayU-Signature). Panel PayU → Klucze konfiguracyjne.',
        'md5_key_status' => 'Status klucza MD5',
        'second_key' => 'Drugi klucz (second_key)',
        'second_key_help' => 'Opcjonalny klucz używany przy weryfikacji status callbacks (legacy formularzowy flow). Większość integracji nie wymaga — można zostawić puste.',
        'second_key_status' => 'Status drugiego klucza',
        'webhook_url' => 'Webhook URL (notyfikacje statusu)',
        'return_url' => 'URL powrotu po płatności',
    ],

    'env' => [
        'sandbox' => 'Sandbox (secure.snd.payu.com)',
        'production' => 'Produkcja (secure.payu.com)',
    ],

    'status' => [
        'configured' => 'Skonfigurowane',
        'not_configured' => 'Nieskonfigurowane',
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację',
        'saved' => 'Konfiguracja PayU zapisana.',
    ],
];
