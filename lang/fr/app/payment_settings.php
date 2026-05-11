<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'default_provider' => 'Prestataire par défaut',
            'default_provider_description' => 'Choisissez la passerelle utilisée par les clients pour payer en ligne. « Aucun » = tout en hors ligne (virement classique / espèces).',
            'p24' => 'Przelewy24',
            'payu' => 'PayU',
            'stripe' => 'Stripe',
            'mollie' => 'Mollie',
        ],
        'label' => [
            'default_provider' => 'Passerelle par défaut',

            // P24
            'p24_merchant_id' => 'Merchant ID',
            'p24_pos_id' => 'POS ID',
            'p24_crc_key' => 'Clé CRC',
            'p24_api_key' => 'Clé API (REST)',
            'p24_api_key_helper' => 'Panneau P24 → Mon entreprise → Points de vente → Configuration → REST API.',
            'p24_sandbox' => 'Sandbox (test)',
            'p24_force_method' => 'Forcer une seule méthode (optionnel)',
            'p24_force_method_helper' => 'Vide = le client voit la liste complète des méthodes dans P24 (recommandé). Sélectionnez une méthode pour rediriger directement vers par exemple BLIK.',
            'force_method_placeholder' => '— Liste complète des méthodes —',

            // PayU
            'payu_pos_id' => 'POS ID (merchantPosId)',
            'payu_client_id' => 'OAuth client_id',
            'payu_client_secret' => 'OAuth client_secret',
            'payu_md5_key' => 'Seconde clé (MD5)',
            'payu_md5_key_helper' => 'Panneau PayU → Points de paiement → Votre POS → « seconde clé (MD5) ».',
            'payu_sandbox' => 'Sandbox (test)',
            'payu_force_method' => 'Forcer une seule méthode (optionnel)',
            'payu_force_method_helper' => 'Vide = le client voit la liste complète des méthodes dans PayU (recommandé). Sélectionnez par exemple BLIK pour rediriger directement vers cette méthode.',

            // Stripe
            'stripe_publishable_key' => 'Publishable key (pk_…)',
            'stripe_secret_key' => 'Secret key (sk_…)',
            'stripe_webhook_secret' => 'Webhook secret (whsec_…)',
            'stripe_webhook_secret_helper' => 'À copier depuis le Stripe Dashboard → Developers → Webhooks → endpoint → Signing secret.',
            'stripe_enabled_methods' => 'Méthodes de paiement affichées',
            'stripe_enabled_methods_helper' => 'Choisissez les options que le client verra dans Stripe Checkout. Par défaut, uniquement les cartes.',

            // Mollie
            'mollie_api_key' => 'Clé API (live_… ou test_…)',
            'mollie_api_key_helper' => 'À récupérer depuis Mollie Dashboard → Developers → API keys.',
            'mollie_enabled_methods' => 'Méthodes de paiement affichées',
            'mollie_enabled_methods_helper' => 'Liste vide = Mollie affichera toutes les méthodes actives sur votre compte. Méthode unique = le client est redirigé directement vers celle-ci (par exemple BLIK).',
        ],
    ],

    'action' => [
        'saved' => 'Paramètres de paiement enregistrés',
    ],
];
