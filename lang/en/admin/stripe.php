<?php

declare(strict_types=1);

return [
    'navigation' => 'Stripe',
    'title' => 'Stripe configuration (SaaS subscriptions)',

    'section' => [
        'env' => 'Environment',
        'env_help' => 'Test = pk_test_/sk_test_ keys (sandbox). Live = pk_live_/sk_live_ — real payments from customers.',
        'keys' => 'API keys',
        'keys_help' => 'From the Stripe panel → Developers → API keys. Publishable goes to the JS frontend; Secret stays server-side. Keys are encrypted (Laravel Crypt) — not visible in plaintext after saving.',
        'webhook' => 'Webhook',
        'webhook_help' => 'Paste the Webhook URL into Stripe → Developers → Webhooks → Add endpoint. After creation, copy the signing secret (whsec_…) here.',
        'events' => 'Events to select in the Stripe panel',
        'events_help' => 'After creating the endpoint in Stripe, in "Select events" mark:',
        'plan_prices' => 'Stripe Price IDs per plan',
        'plan_prices_help' => 'Each plan (Solo/Stable/Pro) needs a Stripe Price ID for the monthly and yearly variant. Create Products + Prices in the Stripe panel, paste the IDs into the plan page.',
        'plan_prices_link' => 'Go to plan list →',
    ],

    'field' => [
        'env' => 'Mode',
        'publishable_key' => 'Publishable key (pk_…)',
        'publishable_key_help' => 'Public key used by Stripe.js / Stripe Checkout on the frontend.',
        'publishable_key_status' => 'Publishable key status',
        'secret_key' => 'Secret key (sk_…)',
        'secret_key_help' => 'Server-side key — NEVER expose publicly. Used to create checkout sessions, refunds, etc.',
        'secret_key_status' => 'Secret key status',
        'webhook_url' => 'Webhook URL',
        'webhook_secret' => 'Webhook signing secret (whsec_…)',
        'webhook_secret_help' => 'Copy from the Stripe panel after creating the webhook endpoint.',
        'webhook_secret_status' => 'Webhook secret status',
    ],

    'env' => [
        'test' => 'Test (sandbox)',
        'live' => 'Live (production)',
    ],

    'status' => [
        'configured' => 'Configured',
        'not_configured' => 'Not configured',
    ],

    'action' => [
        'save_button' => 'Save configuration',
        'saved' => 'Stripe configuration saved.',
    ],
];
