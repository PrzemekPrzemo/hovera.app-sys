<?php

declare(strict_types=1);

return [
    'tokens' => [
        'navigation' => 'Mes jetons API',
        'title' => 'Jetons API personnels du master admin',
        'col' => [
            'name' => 'Nom',
            'abilities' => 'Permissions',
            'last_used_at' => 'Dernière utilisation',
            'created_at' => 'Créé le',
            'expires_at' => 'Expire le',
            'never' => 'jamais',
        ],
        'action' => [
            'generate' => 'Générer un jeton',
            'generate_submit' => 'Générer',
            'revoke' => 'Révoquer',
            'revoke_confirm' => 'Le jeton cessera de fonctionner immédiatement — tous les scripts qui l’utilisent recevront une erreur 401.',
            'revoke_success' => 'Jeton révoqué',
        ],
        'form' => [
            'name' => 'Nom du jeton',
            'name_placeholder' => 'par exemple, Script de monitoring',
            'abilities' => 'Permissions (scopes)',
            'abilities_help' => 'Choisissez le minimum nécessaire au fonctionnement. « admin-all » accorde un accès complet.',
            'expiry' => 'Expiration',
            'expiry_none' => 'Sans expiration',
            'expiry_30d' => '30 jours',
            'expiry_90d' => '90 jours',
            'expiry_1y' => '1 an',
        ],
        'abilities' => [
            'read-tenants' => 'Lecture des écuries (read-tenants)',
            'read-billing' => 'Lecture facturation / Stripe (read-billing)',
            'read-system' => 'Lecture des métriques système (read-system)',
            'admin-impersonate' => 'Imitation d’utilisateurs (admin-impersonate)',
            'admin-all' => 'Accès administrateur complet (admin-all)',
        ],
        'modal' => [
            'heading' => 'Jeton généré',
            'warning' => 'Copiez-le maintenant — vous ne le reverrez plus. En cas de perte, générez-en un nouveau.',
            'name_label' => 'Jeton',
            'copy' => 'Copier dans le presse-papiers',
        ],
    ],

    'tenant_tokens' => [
        'navigation' => 'Jetons API des écuries',
        'title' => 'Jetons API émis pour les écuries',
        'col' => [
            'user' => 'Utilisateur',
            'tenant' => 'Écurie',
            'name' => 'Nom du jeton',
            'abilities' => 'Permissions',
            'last_used_at' => 'Dernière utilisation',
            'created_at' => 'Créé le',
            'ip' => 'IP',
            'user_agent' => 'User-Agent',
        ],
        'filter' => [
            'tenant' => 'Écurie',
            'activity' => 'Activité',
            'active_30d' => 'Actifs (30 jours)',
            'dormant' => 'Inactifs (aucune activité)',
            'any' => 'Tous',
            'created_range' => 'Période de création',
        ],
        'action' => [
            'revoke' => 'Révoquer',
            'revoke_confirm' => 'Le jeton cessera de fonctionner immédiatement. L’application mobile de cet utilisateur devra se reconnecter.',
            'revoke_success' => 'Jeton révoqué',
        ],
        'bulk' => [
            'revoke' => 'Révoquer la sélection',
            'revoked' => ':count jetons révoqués',
        ],
    ],

    'webhooks' => [
        'navigation' => 'Webhooks des écuries',
        'model' => 'Abonnement webhook',
        'model_plural' => 'Webhooks',
        'col' => [
            'tenant' => 'Écurie',
            'url_host' => 'Hôte de l’URL',
            'events' => 'Événements',
            'is_active' => 'Actif',
            'last_delivery' => 'Dernière livraison',
            'last_delivery_at' => 'Date de la dernière livraison',
            'created_at' => 'Créé le',
        ],
        'form' => [
            'section' => [
                'target' => 'Endpoint et événements',
                'signing' => 'Signature des requêtes',
            ],
            'tenant' => 'Écurie',
            'is_active' => 'Actif',
            'url' => 'URL de l’endpoint',
            'url_help' => 'POST vers cette URL lorsque l’un des événements sélectionnés se produit. HTTPS recommandé.',
            'events' => 'Événements',
            'secret' => 'Secret HMAC',
            'secret_regenerated' => 'Nouveau secret généré',
            'signing_help' => 'Chaque requête contient un en-tête X-Hovera-Signature: sha256=<hex> calculé par HMAC sur le corps. Le destinataire doit vérifier la signature avec le même secret.',
        ],
        'filter' => [
            'tenant' => 'Écurie',
            'is_active' => 'Actifs',
        ],
        'action' => [
            'enable' => 'Activer',
            'disable' => 'Désactiver',
            'toggled' => 'État modifié',
        ],
        'deliveries' => [
            'title' => 'Historique des livraisons (50 dernières)',
            'col' => [
                'event' => 'Événement',
                'attempt' => 'Tentative',
                'status' => 'Code HTTP',
                'duration' => 'Durée',
                'delivered_at' => 'Envoyé',
                'error' => 'Erreur',
                'payload' => 'Payload',
            ],
            'action' => [
                'resend' => 'Renvoyer',
                'resent' => 'Nouvelle livraison mise en file d’attente',
            ],
        ],
    ],
];
