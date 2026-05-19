<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Todoist — in-app bug / feedback reporter
    |--------------------------------------------------------------------------
    | App\Services\Integrations\TodoistClient posts a task to the Hovera
    | project whenever a user submits the in-panel bug/idea form.
    */
    'todoist' => [
        'token' => env('TODOIST_API_TOKEN'),
        'hovera_project_id' => env('TODOIST_HOVERA_PROJECT_ID', '6gcqjRCCQwVPWVXg'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe — central billing (hovera SaaS subscription)
    |--------------------------------------------------------------------------
    | Used by App\Services\Billing\StripeBillingService for the central-DB
    | hovera subscription (each tenant pays for hovera itself). NOT the
    | per-tenant payment gateway — that one is in services.payment_providers.
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Przelewy24 — central billing (one-time "opłać fakturę" link)
    |--------------------------------------------------------------------------
    | Used by App\Services\Billing\Przelewy24Service for hovera SaaS invoices
    | (master-admin issues FV → tenant clicks link → P24 hosted payment).
    | Complementary to Stripe subscription. NOT the per-tenant P24 provider
    | (that one lives in tenants.settings.payments.p24, encrypted).
    */
    'przelewy24' => [
        'merchant_id' => env('P24_MERCHANT_ID'),
        'pos_id' => env('P24_POS_ID'),
        'api_key' => env('P24_API_KEY'),
        'crc' => env('P24_CRC'),
        'env' => env('P24_ENV', 'sandbox'),
    ],

    /*
    |--------------------------------------------------------------------------
    | KSeF (central) — hovera as VAT taxpayer
    |--------------------------------------------------------------------------
    | hovera (jako podatnik VAT) wystawia faktury stajniom za subskrypcje;
    | te FV od 2026-02-01 muszą trafiać do KSeF. Cert + hasło żyją w
    | central.system_settings (encrypted via SystemSetting::setSecret).
    | Tutaj tylko domyślny env + NIP fallback.
    */
    'ksef_central' => [
        'env' => env('KSEF_CENTRAL_ENV', 'test'), // test | demo | production
        'context_nip' => env('KSEF_CENTRAL_NIP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | KSeF (per-transporter, handshake flow)
    |--------------------------------------------------------------------------
    | Klucz publiczny MF do RSA-OAEP wrap'a AES-256 klucza sesyjnego.
    | Klucz dystrybuowany jest przez dokumentację KAS (gov.pl) — różny
    | per środowisko, zmienia się rzadko (rotacja co kilka lat).
    |
    | Preferowany flow: ops wgrywa plik PEM do storage/app/ksef/public-key-<env>.pem.
    | Alternatywnie inline w env KSEF_PUBLIC_KEY_TEST_PEM (z literalnymi \n).
    |
    | `public_key_disk` pozwala podmienić disk na 'public' albo s3 jeśli
    | wgrywanie do storage/app/ nie pasuje do deploy modelu.
    */
    'ksef' => [
        'public_key_disk' => env('KSEF_PUBLIC_KEY_DISK', 'local'),
        'public_key' => [
            'test_pem' => env('KSEF_PUBLIC_KEY_TEST_PEM'),
            'demo_pem' => env('KSEF_PUBLIC_KEY_DEMO_PEM'),
            'production_pem' => env('KSEF_PUBLIC_KEY_PRODUCTION_PEM'),
        ],
    ],

];
