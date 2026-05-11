<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'default_provider' => 'Standard-Anbieter',
            'default_provider_description' => 'Wählen Sie, über welches Gateway Ihre Kunden online zahlen sollen. „Keiner" = alles offline (klassische Überweisung / Bargeld).',
            'p24' => 'Przelewy24',
            'payu' => 'PayU',
            'stripe' => 'Stripe',
            'mollie' => 'Mollie',
        ],
        'label' => [
            'default_provider' => 'Standard-Gateway',

            // P24
            'p24_merchant_id' => 'Merchant ID',
            'p24_pos_id' => 'POS ID',
            'p24_crc_key' => 'CRC-Schlüssel',
            'p24_api_key' => 'API-Schlüssel (REST)',
            'p24_api_key_helper' => 'P24-Panel → Meine Firma → Verkaufsstellen → Konfiguration → REST API.',
            'p24_sandbox' => 'Sandbox (Test)',
            'p24_force_method' => 'Nur eine Methode erzwingen (optional)',
            'p24_force_method_helper' => 'Leer = der Kunde sieht die vollständige Methodenliste in P24 (empfohlen). Wählen Sie eine Methode, um direkt z. B. zu BLIK zu leiten.',
            'force_method_placeholder' => '— Vollständige Methodenliste —',

            // PayU
            'payu_pos_id' => 'POS ID (merchantPosId)',
            'payu_client_id' => 'OAuth client_id',
            'payu_client_secret' => 'OAuth client_secret',
            'payu_md5_key' => 'Zweiter Schlüssel (MD5)',
            'payu_md5_key_helper' => 'PayU-Panel → Zahlungspunkte → Ihr POS → „zweiter Schlüssel (MD5)".',
            'payu_sandbox' => 'Sandbox (Test)',
            'payu_force_method' => 'Nur eine Methode erzwingen (optional)',
            'payu_force_method_helper' => 'Leer = der Kunde sieht die vollständige Methodenliste in PayU (empfohlen). Wählen Sie z. B. BLIK, um direkt zu dieser Methode zu leiten.',

            // Stripe
            'stripe_publishable_key' => 'Publishable Key (pk_...)',
            'stripe_secret_key' => 'Secret Key (sk_...)',
            'stripe_webhook_secret' => 'Webhook Secret (whsec_...)',
            'stripe_webhook_secret_helper' => 'Aus dem Stripe Dashboard kopieren → Developers → Webhooks → Endpoint → Signing Secret.',
            'stripe_enabled_methods' => 'Angezeigte Zahlungsmethoden',
            'stripe_enabled_methods_helper' => 'Wählen Sie, welche Optionen der Kunde im Stripe Checkout sieht. Standardmäßig nur Karten.',

            // Mollie
            'mollie_api_key' => 'API-Schlüssel (live_... oder test_...)',
            'mollie_api_key_helper' => 'Aus dem Mollie Dashboard abrufen → Developers → API keys.',
            'mollie_enabled_methods' => 'Angezeigte Zahlungsmethoden',
            'mollie_enabled_methods_helper' => 'Leere Liste = Mollie zeigt alle in Ihrem Konto aktivierten Methoden. Eine einzelne Methode = der Kunde wird direkt zu dieser Methode geleitet (z. B. direkt BLIK).',
        ],
    ],

    'action' => [
        'saved' => 'Zahlungseinstellungen gespeichert',
    ],
];
