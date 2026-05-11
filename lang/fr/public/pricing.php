<?php

declare(strict_types=1);

return [
    'title' => 'Tarifs',
    'meta_description' => 'Tarification transparente de hovera — de 0 zł pour une écurie jusqu’à 5 chevaux à 499 zł pour le plan Pro sans limite. 30 jours gratuits, sans carte.',

    'heading' => 'Des tarifs sans astérisque',
    'lede' => 'Choisissez un plan adapté à la taille de votre écurie. Sans frais cachés, sans engagement et sans carte au départ.',
    'differentiator' => 'La concurrence cache ses tarifs derrière un formulaire. Nous les affichons.',

    'billing_label' => 'Période de facturation',
    'monthly' => 'Mensuel',
    'yearly' => 'Annuel',
    'save_yearly' => 'Économisez environ 10 % par an',
    'most_popular' => 'Le plus populaire',
    'month_short' => 'mois',
    'free_forever' => 'Gratuit pour toujours, sans carte.',
    'billed_monthly' => 'Facturation mensuelle.',
    'billed_yearly_total' => 'Facturation annuelle · :total zł / an',
    'custom_price' => 'Tarif sur devis',
    'custom_price_note' => 'Nous vous répondons sous 24 h.',
    'unlimited' => 'sans limite',
    'vat_notice' => 'Prix hors taxes. 23 % de TVA s’ajoutent à chaque montant.',
    'vat_notice_short' => '+ 23 % TVA',
    'onboarding_fee_label' => 'Frais de mise en route uniques',
    'onboarding_fee_help' => 'Facturés une seule fois au démarrage de l’abonnement.',

    'tagline' => [
        'free' => 'Pour un moniteur indépendant — testez si hovera vous convient.',
        'solo' => 'Pour un moniteur solo avec abonnements et réservation en ligne.',
        'stable' => 'Pour une petite ou moyenne écurie avec facturation et KSeF.',
        'pro' => 'Pour les pensions et grandes écuries — sans limite de clients.',
        'enterprise' => 'Pour les réseaux d’écuries et les franchises — white-label, SSO, SLA.',
    ],

    'cta' => [
        'start_free' => 'Commencer gratuitement',
        'start_trial' => 'Démarrer 30 jours gratuits',
        'contact' => 'Parlons-en',
    ],

    'compare' => [
        'heading' => 'Comparer les plans',
        'sub' => 'Chaque plan inclut tout ce que propose le précédent — la différence se fait sur les limites supplémentaires et les modules.',
        'feature' => 'Fonctionnalité',
        'support_level' => 'Support',
        'group' => [
            'limits' => 'Limites',
            'core' => 'Fonctionnalités',
            'support' => 'Support et SLA',
        ],
        'limits' => [
            'max_horses' => 'Nombre de chevaux',
            'max_clients' => 'Nombre de clients',
            'max_users' => 'Collaborateurs dans le panneau',
            'max_storage_mb' => 'Espace pour photos/documents',
        ],
        'features' => [
            'multi_calendar' => 'Calendrier multi-ressources (moniteur, cheval, manège)',
            'horse_crm' => 'CRM chevaux + clients',
            'online_booking' => 'Réservation en ligne (depuis votre site)',
            'passes' => 'Abonnements et facturation automatique',
            'invoices_ksef' => 'Factures TVA + KSeF',
            'breeding_journal' => 'Journal des juments d’élevage',
            'boarding_portal' => 'Pension + portail propriétaire',
            'public_api' => 'API publique + webhooks',
            'vanity_domain' => 'Domaine personnalisé (par exemple monecurie.fr)',
            'white_label' => 'White-label (logo + identité visuelle)',
            'sso' => 'SSO (Google Workspace / SAML)',
        ],
    ],

    'support' => [
        'community' => 'Communauté',
        'email' => 'E-mail · 48 h',
        'email_chat' => 'E-mail + chat · 24 h',
        'priority' => 'Prioritaire · 4 h en jours ouvrés',
        'dedicated' => 'Responsable dédié · SLA',
    ],

    'faq' => [
        'heading' => 'Questions fréquentes',
        'trial' => [
            'q' => 'Faut-il une carte pour commencer ?',
            'a' => 'Non. Toutes les fonctionnalités pendant 30 jours, sans carte de crédit. Après la période d’essai, vous choisissez un plan uniquement lorsque vous êtes sûr — nous ne basculons jamais automatiquement vers une formule payante.',
        ],
        'change_plan' => [
            'q' => 'Puis-je changer de plan en cours de route ?',
            'a' => 'Oui — à tout moment. La montée en gamme est immédiate, la rétrogradation prend effet à la prochaine période de facturation.',
        ],
        'cancel' => [
            'q' => 'Puis-je résilier ?',
            'a' => 'Oui, à tout moment. Sans engagement, sans préavis. Vous résiliez — vous ne payez pas le mois suivant.',
        ],
        'data_ownership' => [
            'q' => 'À qui appartiennent mes données ?',
            'a' => 'À vous seul. Vous pouvez exporter une sauvegarde complète (calendrier, clients, chevaux, factures) au format CSV/iCal à tout moment — y compris après la résiliation.',
        ],
        'invoice' => [
            'q' => 'Vais-je recevoir une facture avec TVA ?',
            'a' => 'Oui. Nous émettons automatiquement une facture avec TVA à 23 % après chaque paiement — avec le numéro NIP polonais et le détail des prestations.',
        ],
        'limits_exceeded' => [
            'q' => 'Que se passe-t-il si je dépasse la limite de chevaux ?',
            'a' => 'Nous vous prévenons et vous proposons une montée en gamme — nous ne coupons jamais brutalement l’accès en plein cycle. Vous disposez de 30 jours pour décider.',
        ],
    ],

    'nav' => [
        'demo' => 'Démo',
        'login' => 'Connexion',
        'signup' => 'Créer un compte',
    ],

    'footer' => [
        'signup' => 'Créer un compte gratuitement',
        'demo' => 'Voir d’abord la démo',
    ],
];
