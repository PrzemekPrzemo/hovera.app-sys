<?php

declare(strict_types=1);

return [
    'navigation' => 'Przelewy24',
    'title' => 'Przelewy24 configuration',

    'section' => [
        'account' => 'Przelewy24 account',
        'account_help' => 'Shop details from your Przelewy24 panel (panel.przelewy24.pl → My shops → Shop details).',
        'secrets' => 'API keys',
        'secrets_help' => 'Keys are encrypted (Laravel Crypt) and never shown in plaintext after saving. To change — type a new value; an empty field does not overwrite.',
        'webhook' => 'URLs to configure in the P24 panel',
        'webhook_help' => 'Paste these URLs into the Przelewy24 panel → My shops → Configuration → Notifications / Return URL.',
    ],

    'field' => [
        'env' => 'Environment',
        'merchant_id' => 'Merchant ID',
        'merchant_id_help' => '6-digit number from the P24 panel (e.g. 168172).',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Most often identical to merchant ID.',
        'api_key' => 'API key (secret)',
        'api_key_help' => 'Reports key — P24 panel → My shops → Configuration → Keys.',
        'api_key_status' => 'API key status',
        'crc' => 'CRC key (secret)',
        'crc_help' => 'Transaction signing key — P24 panel → My shops → Configuration → Keys → CRC.',
        'crc_status' => 'CRC key status',
        'webhook_url' => 'Webhook (payment status notifications)',
        'return_url' => 'Return URL after payment',
    ],

    'env' => [
        'sandbox' => 'Sandbox (test)',
        'production' => 'Production',
    ],

    'status' => [
        'configured' => 'Configured',
        'not_configured' => 'Not configured',
    ],

    'action' => [
        'save_button' => 'Save configuration',
        'saved' => 'Przelewy24 configuration saved.',
    ],
];
