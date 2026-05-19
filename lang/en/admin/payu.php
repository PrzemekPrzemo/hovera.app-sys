<?php

declare(strict_types=1);

return [
    'navigation' => 'PayU',
    'title' => 'PayU configuration',

    'section' => [
        'account' => 'PayU account',
        'account_help' => 'Point of sale details from your PayU dashboard (panel.payu.com → My shop → Configuration → Points of sale).',
        'secrets' => 'API keys',
        'secrets_help' => 'Keys are encrypted (Laravel Crypt) and never shown in plaintext after save. To change — enter a new value, an empty field does not overwrite.',
        'webhook' => 'URLs to configure in PayU dashboard',
        'webhook_help' => 'Paste the webhook URL into PayU → Points of sale → Configuration → Notification URL (notifyUrl).',
    ],

    'field' => [
        'env' => 'Environment',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Point of sale number (merchantPosId) — PayU dashboard → My shop → Points of sale.',
        'oauth_client_id' => 'OAuth Client ID',
        'oauth_client_id_help' => 'OAuth client identifier for REST API authorization — PayU → Points of sale → Configuration → REST API protocol.',
        'oauth_client_secret' => 'OAuth Client Secret',
        'oauth_client_secret_help' => 'OAuth secret — exchanged for an access_token via grant_type=client_credentials.',
        'oauth_client_secret_status' => 'OAuth Client Secret status',
        'md5_key' => 'Second key (MD5)',
        'md5_key_help' => 'Key used to verify webhook signature (OpenPayU-Signature header). PayU dashboard → Configuration keys.',
        'md5_key_status' => 'MD5 key status',
        'second_key' => 'Second key',
        'second_key_help' => 'Optional key used for legacy form-flow status callback verification. Most integrations do not need it — leave empty.',
        'second_key_status' => 'Second key status',
        'webhook_url' => 'Webhook URL (status notifications)',
        'return_url' => 'Return URL after payment',
    ],

    'env' => [
        'sandbox' => 'Sandbox (secure.snd.payu.com)',
        'production' => 'Production (secure.payu.com)',
    ],

    'status' => [
        'configured' => 'Configured',
        'not_configured' => 'Not configured',
    ],

    'action' => [
        'save_button' => 'Save configuration',
        'saved' => 'PayU configuration saved.',
    ],
];
