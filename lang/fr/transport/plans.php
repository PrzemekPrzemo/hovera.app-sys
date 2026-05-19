<?php

declare(strict_types=1);

/**
 * Marketing source of truth: hovera.app/produkt/transport/.
 * Keys MUST mirror lang/pl/transport/plans.php.
 *
 * Traduit FR — niveau professionnel, vouvoiement. Les noms propres
 * techniques (KSeF, HGV, ORS) restent dans la langue d’origine.
 */
return [
    'page_title' => 'Tarifs pour les transporteurs',
    'meta_description' => 'Tarifs Hovera pour les entreprises de transport de chevaux : à partir de 250 PLN/mois. 4 formules, 5 devises, garantie de blocage tarifaire 12 mois.',

    'heading' => 'Tarifs pour les transporteurs',
    'lede' => 'Choisissez la formule adaptée à la taille de votre entreprise. Chaque formule comprend le calculateur de devis avec routage HGV, les devis PDF avec acceptation publique par le client et un CRM clients. L’essai gratuit d’un mois démarre après la vérification réussie de vos documents.',

    'lock_in_note' => 'Engagement 12 mois — garantie de tarif bloqué',
    'promo_note' => 'Promotion jusqu’au 31/07/2026',

    'most_popular' => 'Le plus populaire',

    'currency_label' => 'Devise',
    'month_short' => 'mois',
    'net_notice' => 'HT par mois, facturé en fin de période',

    'custom_price' => 'Tarif sur mesure',
    'custom_price_note' => 'Tarif établi après un échange avec notre équipe commerciale',
    'price_unavailable' => 'Tarif indisponible en :currency — contactez-nous',

    'cta' => [
        'start_trial' => 'Commencer maintenant',
        'contact' => 'Nous contacter',
        'contact_subject' => 'Hovera Transport Enterprise — demande',
    ],

    'audience_hint' => [
        'default' => '—',
        'small_carriers' => 'Petites entreprises et transporteurs indépendants',
        'growing_carriers' => 'Entreprises en croissance avec une flotte élargie',
        'mid_large_carriers' => 'Moyennes et grandes entreprises',
        'enterprise' => 'Plus de 15 conducteurs / 25 véhicules',
    ],

    'feature' => [
        'calculator_hgv' => 'Calculateur de devis complet avec routage HGV (OpenRouteService)',
        'pdf_quotes_public_acceptance' => 'Devis PDF + acceptation publique par le client + envoi WhatsApp/e-mail',
        'crm_clients' => 'CRM clients avec tarifs personnalisés par client',
        'poi_google_import' => 'POI : lieux personnalisés + import depuis Google Maps',
        'calendar_ical' => 'Calendrier de transports + flux iCal (Google/Apple Calendar)',
        'public_page_pl' => 'Page publique de l’entreprise (PL)',
        'payments_csv_import' => 'Paiements et coûts avec import CSV',
        'invoices_ksef' => 'Factures (KSeF et autres formats)',
        'reports_basic' => 'Rapports : conducteurs, clients, véhicules, trésorerie',
        'support_email_24h' => 'Support par e-mail · réponse sous 24 h',

        'multilang_public_page' => 'Page publique multilingue (PL + EN + DE)',
        'custom_rates_per_client' => 'Tarifs et forfaits minimums personnalisés par client',
        'auto_toll_estimation' => 'Estimation automatique des péages (ORS tollways)',
        'stop_types_dictionary' => 'Dictionnaire des types d’arrêt (chargement/déchargement/vétérinaire/nuit)',
        'public_gallery' => 'Galerie publique avec photos de transport',

        'custom_branding' => 'Branding personnalisé (logo + couleurs sur la page publique et les PDF)',
        'advanced_reports' => 'Rapports avancés : marges, meilleures routes, popularité des itinéraires',
        'export_csv_json_gdpr' => 'Export CSV/JSON de toutes les données (RGPD art. 20)',
        'configurable_toll_rates' => 'Tarifs de péage configurables (VL vs PL)',
        'roadmap_priority' => 'Priorité sur la roadmap (vote des fonctionnalités)',

        'dedicated_environment' => 'Environnement dédié (VPS séparé)',
        'sla_financial_99_9' => 'SLA 99,9 % avec garantie financière',
        'live_onboarding' => 'Onboarding en direct avec un formateur (2 à 4 h)',
        'data_migration_free' => 'Migration des données — gratuite',
        'white_label' => 'White-label (système sous la marque du client)',
        'api_rest' => 'API REST pour les intégrations',
        'dedicated_storage' => 'Sauvegarde sur stockage dédié (S3 / GDrive)',
        'custom_integrations' => 'Intégrations sur mesure (CRM / ERP / comptabilité)',
    ],

    'addons_heading' => 'Options',
    'addons_sub' => 'Toutes les options sont globales — disponibles quelle que soit la formule choisie.',
    'addons_table' => [
        'name' => 'Option',
        'type' => 'Facturation',
        'price' => 'Prix',
    ],
    'addon_type' => [
        'one_time' => 'unique',
        'recurring_monthly' => 'mensuel',
    ],

    'nav' => [
        'stable_pricing' => 'Tarifs écuries',
        'signup' => 'S’inscrire',
    ],
    'footer' => [
        'signup' => 'S’inscrire',
        'terms' => 'Conditions',
    ],
];
