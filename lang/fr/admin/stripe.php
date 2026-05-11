<?php

declare(strict_types=1);

return [
    'navigation' => 'Stripe',
    'title' => 'Configuration Stripe (abonnements SaaS)',

    'section' => [
        'env' => 'Environnement',
        'env_help' => 'Test = clés pk_test_ / sk_test_ (sandbox). Live = clés pk_live_ / sk_live_ — vrais paiements clients.',
        'keys' => 'Clés API',
        'keys_help' => 'Depuis le tableau de bord Stripe → Developers → API keys. La clé Publishable est utilisée par le frontend JS ; la clé Secret reste côté serveur. Les clés sont chiffrées (Laravel Crypt) — non visibles en clair après enregistrement.',
        'webhook' => 'Webhook',
        'webhook_help' => 'Collez l’URL du webhook dans Stripe → Developers → Webhooks → Add endpoint. Après création, copiez ici le signing secret (whsec_…).',
        'events' => 'Événements à sélectionner dans le tableau de bord Stripe',
        'events_help' => 'Après avoir créé l’endpoint dans Stripe, dans « Select events », cochez :',
        'plan_prices' => 'Stripe Price IDs par plan',
        'plan_prices_help' => 'Chaque plan (Solo/Stable/Pro) doit avoir un Stripe Price ID pour la variante mensuelle et la variante annuelle. Créez les Products et Prices dans le tableau de bord Stripe, puis collez les ID dans la page du plan.',
        'plan_prices_link' => 'Aller à la liste des plans →',
    ],

    'field' => [
        'env' => 'Mode',
        'publishable_key' => 'Publishable key (pk_…)',
        'publishable_key_help' => 'Clé publique utilisée par le frontend Stripe.js / Stripe Checkout.',
        'publishable_key_status' => 'Statut de la clé publishable',
        'secret_key' => 'Secret key (sk_…)',
        'secret_key_help' => 'Clé serveur — ne JAMAIS exposer publiquement. Utilisée pour créer des sessions de paiement, des remboursements, etc.',
        'secret_key_status' => 'Statut de la clé secret',
        'webhook_url' => 'URL du webhook',
        'webhook_secret' => 'Webhook signing secret (whsec_…)',
        'webhook_secret_help' => 'À copier depuis le tableau de bord Stripe après la création du webhook endpoint.',
        'webhook_secret_status' => 'Statut du webhook secret',
    ],

    'env' => [
        'test' => 'Test (sandbox)',
        'live' => 'Live (production)',
    ],

    'status' => [
        'configured' => 'Configuré',
        'not_configured' => 'Non configuré',
    ],

    'action' => [
        'save_button' => 'Enregistrer la configuration',
        'saved' => 'Configuration Stripe enregistrée.',
    ],
];
