<?php

declare(strict_types=1);

return [
    'navigation' => [
        'label' => 'Abonnement hovera',
    ],
    'page' => [
        'title' => 'Abonnement hovera',
        'subtitle' => 'Choisissez un plan pour l’écurie :stable. Paiement récurrent par carte — vous pouvez annuler à tout moment.',
        'redirecting' => 'Redirection vers la page de facturation…',
        'click_here' => 'Si votre navigateur n’a pas redirigé automatiquement, cliquez ici.',
    ],
    'status' => [
        'active' => 'Abonnement actif',
        'trial_expired' => 'Période d’essai expirée — choisissez un plan',
        'trial_days_left' => '{1} :days jour d’essai restant|[2,*] :days jours d’essai restants',
    ],
    'period' => [
        'label' => 'Période de facturation',
        'monthly' => 'Mensuel',
        'yearly' => 'Annuel (-10 %)',
        'month_short' => 'mois',
        'year_short' => 'an',
        'one_time' => 'Paiement unique',
    ],
    'actions' => [
        'choose' => 'Choisir ce plan',
        'current' => 'Votre plan actuel',
        'manage' => 'Gérer l’abonnement',
        'back_to_app' => 'Retour à l’application',
    ],
    'manage' => [
        'title' => 'Gestion de l’abonnement',
        'description' => 'Modifiez votre carte, téléchargez vos factures ou annulez votre abonnement depuis le portail Stripe.',
    ],
    'return' => [
        'title' => 'Abonnement',
        'success_title' => 'Abonnement actif',
        'success_body' => 'Merci ! Votre abonnement hovera est actif — le reçu vous sera envoyé par e-mail.',
        'go_to_app' => 'Accéder à l’application',
        'pending_title' => 'Traitement du paiement',
        'pending_body' => 'Stripe confirme le paiement — cela peut prendre quelques secondes. Veuillez actualiser la page dans un instant.',
        'refresh' => 'Actualiser',
    ],
    'errors' => [
        'unknown_plan' => 'Le plan sélectionné n’existe pas ou est inactif.',
        'checkout_failed' => 'Impossible de créer la session de paiement. Veuillez réessayer ou nous contacter.',
        'portal_failed' => 'Impossible d’ouvrir le portail de facturation. Veuillez nous contacter.',
    ],
    'footer' => [
        'disclaimer' => 'Les paiements sont traités par Stripe. Vos données de carte ne sont jamais stockées sur les serveurs hovera. Les factures avec TVA sont générées automatiquement après chaque paiement réussi.',
    ],
    'suggested_badge' => 'Recommandé',
    'trial_banner' => [
        'expires_today' => 'Votre période d’essai se termine aujourd’hui.',
        'expires_tomorrow' => 'Votre période d’essai se termine demain.',
        'days_left' => '{1} :days jour d’essai restant.|[2,*] :days jours d’essai restants.',
        'pro_pitch' => 'Vous bénéficiez de toutes les fonctionnalités Pro, mais la période d’essai est limitée à :horses chevaux et :clients clients. Choisissez le plan Pro pour lever cette limite.',
        'cta_pro' => 'Choisir Pro',
    ],
    'limits' => [
        'title' => 'Limite du plan atteinte',
        'horses_exceeded' => 'Essai : limite de :limit chevaux — choisissez un plan pour en ajouter davantage.',
        'clients_exceeded' => 'Essai : limite de :limit clients — choisissez un plan pour en ajouter davantage.',
        'vehicles_exceeded' => 'Limite de :limit véhicules dans le plan actuel — passez à un plan supérieur pour en ajouter.',
        'drivers_exceeded' => 'Limite de :limit chauffeurs dans le plan actuel — passez à un plan supérieur pour en ajouter.',
    ],
    'onboarding_fee' => [
        'label' => 'Frais de mise en route — plan :plan',
        'description' => 'Frais d’activation uniques facturés au démarrage de l’abonnement.',
    ],
    'onboarding_fee_label' => 'paiement unique (frais de mise en route)',
    'vat_notice' => 'Prix hors taxes. 23 % de TVA s’ajoutent à chaque montant.',
    'vat_notice_short' => '+ 23 % TVA',
    'email' => [
        'invoice_paid' => [
            'subject' => 'Facture :number — payée, merci !',
            'heading' => 'Facture :number payée',
            'intro' => 'Merci ! Nous avons bien reçu le paiement de votre abonnement hovera pour l’écurie :stable.',
            'field_number' => 'Numéro de facture',
            'field_plan' => 'Plan',
            'field_period' => 'Période',
            'field_total' => 'Montant TTC',
            'field_paid_at' => 'Payé le',
            'pdf_pending' => 'Le PDF de la facture apparaîtra prochainement dans votre panneau de facturation. Elle sera également transmise à KSeF (si configuré).',
            'cta_billing' => 'Ouvrir le panneau de facturation',
            'thanks' => 'Nous sommes ravis de vous compter parmi nous !',
            'signoff' => 'Cordialement,',
        ],
    ],
];
