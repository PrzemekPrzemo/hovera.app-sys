<?php

declare(strict_types=1);

/**
 * Marketing source of truth: hovera.app/produkt/transport/.
 * Keys MUST mirror lang/pl/transport/plans.php.
 *
 * Translated DE — business-grade German, formal "Sie". Technische Begriffe
 * (KSeF, HGV, ORS) bleiben im Original, da es sich um Eigennamen handelt.
 */
return [
    'page_title' => 'Preise für Transportunternehmen',
    'meta_description' => 'Hovera-Preise für Pferdetransport-Unternehmen: ab 250 PLN/Monat. 4 Tarife, 5 Währungen, 12-monatige Preisgarantie.',

    'heading' => 'Preise für Transportunternehmen',
    'lede' => 'Wählen Sie einen Tarif, der zum Umfang Ihres Unternehmens passt. Jeder Tarif enthält den HGV-Routing-Angebotsrechner, PDF-Angebote mit öffentlicher Annahme durch den Kunden sowie ein Kunden-CRM. Die einmonatige kostenlose Testphase beginnt nach erfolgreicher Dokumenten-Verifizierung.',

    'lock_in_note' => '12-Monats-Bindung — Preisgarantie',
    'promo_note' => 'Aktion bis 31.07.2026',

    'most_popular' => 'Am beliebtesten',

    'currency_label' => 'Währung',
    'month_short' => 'Mon.',
    'net_notice' => 'netto pro Monat, abgerechnet am Periodenende',

    'custom_price' => 'Individueller Preis',
    'custom_price_note' => 'Preis nach Gespräch mit unserem Vertrieb',
    'price_unavailable' => 'Preis in :currency nicht verfügbar — kontaktieren Sie uns',

    'cta' => [
        'start_trial' => 'Jetzt starten',
        'contact' => 'Kontakt aufnehmen',
        'contact_subject' => 'Hovera Transport Enterprise — Anfrage',
    ],

    'audience_hint' => [
        'default' => '—',
        'small_carriers' => 'Kleine Unternehmen und Einzelunternehmer',
        'growing_carriers' => 'Wachsende Unternehmen mit größerem Fuhrpark',
        'mid_large_carriers' => 'Mittlere und große Unternehmen',
        'enterprise' => 'Über 15 Fahrer / 25 Fahrzeuge',
    ],

    'feature' => [
        'calculator_hgv' => 'Vollständiger Angebotsrechner mit HGV-Routing (OpenRouteService)',
        'pdf_quotes_public_acceptance' => 'PDF-Angebote + öffentliche Annahme durch Kunden + Versand per WhatsApp/E-Mail',
        'crm_clients' => 'Kunden-CRM mit individuellen Tarifen je Kunde',
        'poi_google_import' => 'POI: eigene Orte + Import aus Google Maps',
        'calendar_ical' => 'Transportkalender + iCal-Feed (Google/Apple Kalender)',
        'public_page_pl' => 'Öffentliche Firmenseite (PL)',
        'payments_csv_import' => 'Zahlungen + Kosten mit CSV-Import',
        'invoices_ksef' => 'Rechnungen (KSeF und weitere Formate)',
        'reports_basic' => 'Berichte: Fahrer, Kunden, Fahrzeuge, Cashflow',
        'support_email_24h' => 'E-Mail-Support · Antwortzeit 24 Stunden',

        'multilang_public_page' => 'Mehrsprachige öffentliche Seite (PL + EN + DE)',
        'custom_rates_per_client' => 'Kundenspezifische Tarife und Mindestbeträge',
        'auto_toll_estimation' => 'Automatische Mautberechnung (ORS Tollways)',
        'stop_types_dictionary' => 'Stopp-Typ-Wörterbuch (Beladung/Entladung/Tierarzt/Übernachtung)',
        'public_gallery' => 'Öffentliche Galerie mit Transportfotos',

        'custom_branding' => 'Eigenes Branding (Logo + Farben auf öffentlicher Seite und PDFs)',
        'advanced_reports' => 'Erweiterte Berichte: Margen, Top-Routen, Routen-Popularität',
        'export_csv_json_gdpr' => 'CSV/JSON-Export aller Daten (DSGVO Art. 20)',
        'configurable_toll_rates' => 'Konfigurierbare Mautsätze (PKW vs. LKW)',
        'roadmap_priority' => 'Roadmap-Priorität (Feature-Voting)',

        'dedicated_environment' => 'Dedizierte Umgebung (eigener VPS)',
        'sla_financial_99_9' => '99,9 % SLA mit finanzieller Garantie',
        'live_onboarding' => 'Live-Onboarding mit Trainer (2–4 Std.)',
        'data_migration_free' => 'Datenmigration — kostenlos',
        'white_label' => 'White-Label (System unter Kundenmarke)',
        'api_rest' => 'REST-API für Integrationen',
        'dedicated_storage' => 'Backup auf dediziertem Speicher (S3 / GDrive)',
        'custom_integrations' => 'Individuelle Integrationen (CRM / ERP / Buchhaltung)',
    ],

    'addons_heading' => 'Zusatzleistungen',
    'addons_sub' => 'Alle Zusatzleistungen sind global — verfügbar unabhängig vom gewählten Tarif.',
    'addons_table' => [
        'name' => 'Zusatzleistung',
        'type' => 'Abrechnung',
        'price' => 'Preis',
    ],
    'addon_type' => [
        'one_time' => 'einmalig',
        'recurring_monthly' => 'monatlich',
    ],

    'nav' => [
        'stable_pricing' => 'Preise für Ställe',
        'signup' => 'Registrieren',
    ],
    'footer' => [
        'signup' => 'Registrieren',
        'terms' => 'AGB',
    ],
];
