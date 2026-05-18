<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identification',
            'pricing' => 'Tarifs',
            'stripe' => 'Stripe Price IDs',
            'stripe_description' => 'Identifiants de prix depuis le tableau de bord Stripe (Products → Pricing). Requis pour les abonnements Stripe Checkout. Sans eux, le client ne peut pas payer.',
            'limits' => 'Limites',
            'limits_description' => 'Limites strictes du plan — appliquées dans l’application (CreateTenant bloque lorsque la limite est dépassée).',
            'features' => 'Fonctionnalités',
            'features_description' => 'Liste des points marketing et des indicateurs de fonctionnalités pour le système de feature flags.',
            'visibility' => 'Visibilité',
        ],
        'helper' => [
            'code' => 'Identifiant unique (par exemple free, stable, pro). Utilisé dans l’API et les liens.',
            'sort_order' => 'Plus la valeur est basse, plus l’élément est haut dans la liste.',
            'price_yearly' => 'Généralement 10× le tarif mensuel, moins 10 à 30 % de remise annuelle.',
            'stripe_price_monthly_id' => 'À copier depuis le tableau de bord Stripe → Products → plan concerné → Pricing → cliquez sur le price ID.',
            'stripe_price_yearly_id' => 'Second Price ID pour la variante annuelle (généralement Recurring → Yearly).',
            'onboarding_fee' => 'Frais d’activation uniques facturés au premier checkout. Requis pour tout plan payant (laissez vide ou à 0 pour le plan Free).',
            'limits' => 'Clés standard : max_horses, max_clients, max_users, max_storage_mb. -1 = illimité.',
            'features' => 'Clés : bullets[N]=string (marketing), enabled.X=bool (feature flag).',
            'is_active' => 'Indique si le plan peut encore être assigné à de nouveaux tenants.',
            'is_public' => 'Indique s’il doit apparaître sur la page de tarification publique. Enterprise généralement false (sur mesure).',
            'audience' => 'À qui s’adresse ce plan — Écurie ou Transporteur. Immuable après création.',
        ],
        'label' => [
            'audience' => 'Audience',
            'price_monthly' => 'Prix mensuel',
            'price_yearly' => 'Prix annuel',
            'stripe_price_monthly_id' => 'Stripe Price ID (mensuel)',
            'stripe_price_yearly_id' => 'Stripe Price ID (annuel)',
            'onboarding_fee' => 'Frais de mise en route (paiement unique)',
            'is_active' => 'Actif',
            'is_public' => 'Public dans la tarification',
            'kv_key' => 'Clé',
            'kv_value' => 'Valeur',
        ],
    ],

    'table' => [
        'column' => [
            'audience' => 'Audience',
            'price_monthly' => 'Mensuel',
            'price_yearly' => 'Annuel',
            'tenants_count' => 'Écuries',
            'is_active_short' => 'Act.',
            'is_public_short' => 'Pub.',
        ],
        'filter' => [
            'audience' => 'Audience',
        ],
    ],

    'action' => [
        'delete_blocked_title' => 'Impossible de supprimer — ce plan est utilisé.',
        'delete_blocked_body' => ':count écuries utilisent ce plan. Réaffectez-les d’abord à un autre plan.',
    ],
];
