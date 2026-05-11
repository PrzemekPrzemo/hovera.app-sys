<?php

declare(strict_types=1);

return [
    'navigation' => 'Stripe',
    'title' => 'Stripe-Konfiguration (SaaS-Abonnements)',

    'section' => [
        'env' => 'Umgebung',
        'env_help' => 'Test = Schlüssel pk_test_/sk_test_ (Sandbox). Live = Schlüssel pk_live_/sk_live_ — echte Zahlungen von Kunden.',
        'keys' => 'API-Schlüssel',
        'keys_help' => 'Aus dem Stripe-Panel → Developers → API keys. Publishable geht ins JS-Frontend; Secret bleibt serverseitig. Schlüssel werden verschlüsselt (Laravel Crypt) — nach dem Speichern nicht mehr im Klartext sichtbar.',
        'webhook' => 'Webhook',
        'webhook_help' => 'Fügen Sie die Webhook-URL im Stripe-Panel ein → Developers → Webhooks → Add endpoint. Nach dem Erstellen kopieren Sie das Signing-Secret (whsec_…) hierher.',
        'events' => 'Im Stripe-Panel zu markierende Events',
        'events_help' => 'Nach dem Erstellen des Endpoints in Stripe markieren Sie unter "Select events":',
        'plan_prices' => 'Stripe Price IDs pro Tarif',
        'plan_prices_help' => 'Jeder Tarif (Solo/Stable/Pro) benötigt eine Stripe Price ID für die monatliche und jährliche Variante. Erstellen Sie Products + Prices im Stripe-Panel und fügen Sie die IDs auf der Tarifseite ein.',
        'plan_prices_link' => 'Zur Tarifliste →',
    ],

    'field' => [
        'env' => 'Modus',
        'publishable_key' => 'Publishable Key (pk_…)',
        'publishable_key_help' => 'Öffentlicher Schlüssel, vom Frontend Stripe.js / Stripe Checkout verwendet.',
        'publishable_key_status' => 'Status Publishable Key',
        'secret_key' => 'Secret Key (sk_…)',
        'secret_key_help' => 'Serverseitiger Schlüssel — NIEMALS öffentlich preisgeben. Wird zum Erstellen von Checkout Sessions, Refunds usw. verwendet.',
        'secret_key_status' => 'Status Secret Key',
        'webhook_url' => 'Webhook-URL',
        'webhook_secret' => 'Webhook Signing Secret (whsec_…)',
        'webhook_secret_help' => 'Aus dem Stripe-Panel nach Erstellen des Webhook-Endpoints kopieren.',
        'webhook_secret_status' => 'Status Webhook Secret',
    ],

    'env' => [
        'test' => 'Test (Sandbox)',
        'live' => 'Live (Produktion)',
    ],

    'status' => [
        'configured' => 'Konfiguriert',
        'not_configured' => 'Nicht konfiguriert',
    ],

    'action' => [
        'save_button' => 'Konfiguration speichern',
        'saved' => 'Stripe-Konfiguration gespeichert.',
    ],
];
