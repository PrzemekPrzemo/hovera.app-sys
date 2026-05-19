<?php

declare(strict_types=1);

/**
 * @todo native review — currently uses EN copy as placeholder.
 * Marketing source of truth: hovera.app/produkt/transport/.
 * Keys MUST mirror lang/pl/transport/plans.php.
 */
return [
    'page_title' => 'Pricing for carrier companies',
    'meta_description' => 'Hovera pricing for horse transport companies: Start from PLN 250/mo. 4 plans, 5 currencies, 12-month price-lock guarantee.',

    'heading' => 'Pricing for carrier companies',
    'lede' => 'Pick a plan that matches the scale of your business. Every plan includes the HGV routing quote calculator, PDF quotes with public client acceptance and a customer CRM. 1-month free trial starts after successful document verification.',

    'lock_in_note' => '12-month lock-in — price-lock guarantee',
    'promo_note' => 'Promo until 2026-07-31',

    'most_popular' => 'Most popular',

    'currency_label' => 'Currency',
    'month_short' => 'mo',
    'net_notice' => 'net per month, billed at period end',

    'custom_price' => 'Custom pricing',
    'custom_price_note' => 'Price agreed after a call with our sales team',
    'price_unavailable' => 'Price unavailable in :currency — contact us',

    'cta' => [
        'start_trial' => 'Start now',
        'contact' => 'Contact us',
        'contact_subject' => 'Hovera Transport Enterprise — inquiry',
    ],

    'audience_hint' => [
        'default' => '—',
        'small_carriers' => 'Small businesses and individual carriers',
        'growing_carriers' => 'Growing companies with a larger fleet',
        'mid_large_carriers' => 'Medium and large companies',
        'enterprise' => 'Above 15 drivers / 25 vehicles',
    ],

    'feature' => [
        'calculator_hgv' => 'Full quote calculator with HGV routing (OpenRouteService)',
        'pdf_quotes_public_acceptance' => 'PDF quotes + public client acceptance + WhatsApp/email distribution',
        'crm_clients' => 'Customer CRM with per-client custom rates',
        'poi_google_import' => 'POI: custom places + Google Maps import',
        'calendar_ical' => 'Transport calendar + iCal feed (Google/Apple Calendar)',
        'public_page_pl' => 'Public company page (PL)',
        'payments_csv_import' => 'Payments + costs with CSV import',
        'invoices_ksef' => 'Invoices (KSeF and other formats)',
        'reports_basic' => 'Reports: drivers, customers, vehicles, cash-flow',
        'support_email_24h' => 'Email support · 24h response time',

        'multilang_public_page' => 'Multilingual public page (PL + EN + DE)',
        'custom_rates_per_client' => 'Per-client custom rates and minimums',
        'auto_toll_estimation' => 'Auto toll estimation (ORS tollways)',
        'stop_types_dictionary' => 'Stop type dictionary (loading/unloading/vet/overnight)',
        'public_gallery' => 'Public gallery with transport photos',

        'custom_branding' => 'Custom branding (logo + colors on public page and PDFs)',
        'advanced_reports' => 'Advanced reports: margins, top routes, route popularity',
        'export_csv_json_gdpr' => 'CSV/JSON export of all data (GDPR art. 20)',
        'configurable_toll_rates' => 'Configurable toll rates (light vs HGV)',
        'roadmap_priority' => 'Roadmap priority (feature voting)',

        'dedicated_environment' => 'Dedicated environment (separate VPS)',
        'sla_financial_99_9' => '99.9% SLA with financial guarantee',
        'live_onboarding' => 'Live onboarding with a trainer (2–4 h)',
        'data_migration_free' => 'Data migration — free',
        'white_label' => 'White-label (system under client brand)',
        'api_rest' => 'REST API for integrations',
        'dedicated_storage' => 'Backup to dedicated storage (S3 / GDrive)',
        'custom_integrations' => 'Custom integrations (CRM / ERP / accounting)',
    ],

    'addons_heading' => 'Add-ons',
    'addons_sub' => 'All add-ons are global — available regardless of the plan you pick.',
    'addons_table' => [
        'name' => 'Add-on',
        'type' => 'Billing',
        'price' => 'Price',
    ],
    'addon_type' => [
        'one_time' => 'one-time',
        'recurring_monthly' => 'monthly',
    ],

    'nav' => [
        'stable_pricing' => 'Stables pricing',
        'signup' => 'Sign up',
    ],
    'footer' => [
        'signup' => 'Sign up',
        'terms' => 'Terms',
    ],
];
