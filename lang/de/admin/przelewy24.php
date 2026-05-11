<?php

declare(strict_types=1);

return [
    'navigation' => 'Przelewy24',
    'title' => 'Przelewy24-Konfiguration',

    'section' => [
        'account' => 'Przelewy24-Konto',
        'account_help' => 'Shop-Daten aus dem Przelewy24-Panel (panel.przelewy24.pl → Meine Shops → Shop-Daten).',
        'secrets' => 'API-Schlüssel',
        'secrets_help' => 'Die Schlüssel werden verschlüsselt (Laravel Crypt) und nach dem Speichern nie im Klartext angezeigt. Zum Ändern einen neuen Wert eingeben; ein leeres Feld überschreibt nicht.',
        'webhook' => 'Im P24-Panel zu konfigurierende URLs',
        'webhook_help' => 'Fügen Sie diese URLs im Przelewy24-Panel ein → Meine Shops → Konfiguration → Benachrichtigungseinstellungen / Rücksprung-URL.',
    ],

    'field' => [
        'env' => 'Umgebung',
        'merchant_id' => 'Merchant-ID',
        'merchant_id_help' => '6-stellige Nummer aus dem P24-Panel (z. B. 168172).',
        'pos_id' => 'POS-ID',
        'pos_id_help' => 'Meist identisch mit der Merchant-ID.',
        'api_key' => 'API-Schlüssel (Secret)',
        'api_key_help' => 'Reports-Schlüssel — P24-Panel → Meine Shops → Konfiguration → Schlüssel.',
        'api_key_status' => 'API-Schlüssel-Status',
        'crc' => 'CRC-Schlüssel (Secret)',
        'crc_help' => 'Schlüssel zur Transaktionssignatur — P24-Panel → Meine Shops → Konfiguration → Schlüssel → CRC.',
        'crc_status' => 'CRC-Schlüssel-Status',
        'webhook_url' => 'Webhook (Status-Benachrichtigungen)',
        'return_url' => 'Rücksprung-URL nach Zahlung',
    ],

    'env' => [
        'sandbox' => 'Sandbox (Test)',
        'production' => 'Produktion',
    ],

    'status' => [
        'configured' => 'Konfiguriert',
        'not_configured' => 'Nicht konfiguriert',
    ],

    'action' => [
        'save_button' => 'Konfiguration speichern',
        'saved' => 'Przelewy24-Konfiguration gespeichert.',
    ],
];
