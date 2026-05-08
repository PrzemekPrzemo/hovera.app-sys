<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'default_provider' => 'Default provider',
            'default_provider_description' => 'Pick the gateway clients use to pay online. "None" = everything offline (bank transfer / cash).',
            'p24' => 'Przelewy24',
            'payu' => 'PayU',
            'stripe' => 'Stripe',
            'mollie' => 'Mollie',
        ],
        'label' => [
            'default_provider' => 'Default gateway',

            // P24
            'p24_merchant_id' => 'Merchant ID',
            'p24_pos_id' => 'POS ID',
            'p24_crc_key' => 'CRC key',
            'p24_api_key' => 'API key (REST)',
            'p24_api_key_helper' => 'P24 panel → My company → Points of sale → Configuration → REST API.',
            'p24_sandbox' => 'Sandbox (test)',
            'p24_force_method' => 'Force a single method (optional)',
            'p24_force_method_helper' => 'Empty = client sees the full list of methods in P24 (recommended). Pick a single method to route directly to e.g. BLIK.',
            'force_method_placeholder' => '— Full method list —',

            // PayU
            'payu_pos_id' => 'POS ID (merchantPosId)',
            'payu_client_id' => 'OAuth client_id',
            'payu_client_secret' => 'OAuth client_secret',
            'payu_md5_key' => 'Second key (MD5)',
            'payu_md5_key_helper' => 'PayU panel → Payment points → Your POS → "second key (MD5)".',
            'payu_sandbox' => 'Sandbox (test)',
            'payu_force_method' => 'Force a single method (optional)',
            'payu_force_method_helper' => 'Empty = client sees the full list in PayU (recommended). Pick e.g. BLIK to route directly to that method.',

            // Stripe
            'stripe_publishable_key' => 'Publishable key (pk_...)',
            'stripe_secret_key' => 'Secret key (sk_...)',
            'stripe_webhook_secret' => 'Webhook secret (whsec_...)',
            'stripe_webhook_secret_helper' => 'Copy from Stripe Dashboard → Developers → Webhooks → endpoint → Signing secret.',
            'stripe_enabled_methods' => 'Visible payment methods',
            'stripe_enabled_methods_helper' => 'Pick which methods clients see in Stripe Checkout. Cards only by default.',

            // Mollie
            'mollie_api_key' => 'API key (live_... or test_...)',
            'mollie_api_key_helper' => 'Get one from Mollie Dashboard → Developers → API keys.',
            'mollie_enabled_methods' => 'Visible payment methods',
            'mollie_enabled_methods_helper' => 'Empty list = Mollie shows all methods active on your account. Single method = client goes straight to that method (e.g. straight to BLIK).',
        ],
    ],

    'action' => [
        'saved' => 'Payment settings saved',
    ],
];
