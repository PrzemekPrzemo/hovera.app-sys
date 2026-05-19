<?php

declare(strict_types=1);

return [
    'payment_method_label' => 'Stripe (carte / BLIK / Przelewy24)',

    'section' => [
        'title' => 'Stripe Connect Express (paiements en ligne)',
        'description' => 'Activation en un clic — votre propre compte Stripe Express, l\'argent va directement à vous. Paiement en ligne pour chaque devis, automatiquement.',
        'disclaimer' => 'Stripe Connect Express : VOTRE compte Stripe, VOTRE contrat avec Stripe (KYC chez Stripe). Hovera permet uniquement techniquement le checkout — l\'argent va directement à vous. Hovera peut (par défaut ne le fait pas) prélever une commission de transaction — voir §15 des conditions marketplace.',
    ],

    'form' => [
        'label' => [
            'status' => 'État de l\'intégration',
        ],
    ],

    'status' => [
        'none' => 'Non connecté',
        'pending' => 'Vérification en cours chez Stripe',
        'enabled' => 'Actif — vous pouvez accepter des paiements',
        'restricted' => 'Restreint — complétez les données chez Stripe',
        'rejected' => 'Rejeté — contactez le support Stripe',
    ],

    'action' => [
        'connect' => 'Connecter le compte Stripe',
        'refresh_status' => 'Vérifier le statut',
        'open_dashboard' => 'Ouvrir le tableau de bord Stripe',
        'admin_sync' => 'Synchroniser le statut Stripe',
    ],

    'notify' => [
        'onboard_failed' => 'Impossible de démarrer l\'onboarding Stripe.',
        'status_sync_failed' => 'Impossible de synchroniser le statut Stripe.',
        'dashboard_failed' => 'Impossible de générer le lien du tableau de bord Stripe.',
        'refreshed' => 'Statut Stripe mis à jour.',
        'status_none' => 'Aucun compte Stripe — cliquez sur « Connecter le compte Stripe ».',
        'status_pending' => 'KYC en cours — Stripe vérifie les données de l\'entreprise. Réessayez sous peu.',
        'status_enabled' => 'Compte Stripe actif — vous pouvez émettre des devis avec paiement en ligne.',
        'status_restricted' => 'Stripe a restreint le compte — vérifiez le tableau de bord et complétez les données manquantes.',
        'status_rejected' => 'Stripe a rejeté le compte — contact avec le support Stripe requis.',
    ],
];
