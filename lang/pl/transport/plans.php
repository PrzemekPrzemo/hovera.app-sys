<?php

declare(strict_types=1);

/**
 * Marketing copy planów transportowych. Source of truth: marketing spec
 * (hovera.app/produkt/transport/ — komponent `CarrierOnboarding.astro`).
 * PL = primary; tłumaczenia (en/de/fr/ru) trzymają strukturę kluczy.
 */
return [
    'page_title' => 'Cennik dla firm transportowych',
    'meta_description' => 'Cennik Hovera dla firm transportu konnego: Start od 250 zł/mc. 4 plany, 5 walut, gwarancja niezmienności ceny 12 miesięcy.',

    'heading' => 'Cennik dla firm transportowych',
    'lede' => 'Wybierz plan dopasowany do skali Twojej firmy. Wszystkie plany zawierają kalkulator wycen z routingiem HGV, oferty PDF z publiczną akceptacją oraz CRM klientów. Trial 1 miesiąc gratis startuje od pozytywnej weryfikacji dokumentów.',

    'lock_in_note' => 'Lock-in 12 mc — gwarancja niezmienności ceny',
    'promo_note' => 'Promocja do 31.07.2026',

    'most_popular' => 'Najczęściej wybierany',

    'currency_label' => 'Waluta',
    'month_short' => 'mc',
    'net_notice' => 'netto / miesiąc, fakturujemy na koniec okresu',

    'custom_price' => 'Indywidualnie',
    'custom_price_note' => 'Cena ustalana po rozmowie z zespołem sprzedaży',
    'price_unavailable' => 'Cena niedostępna w :currency — skontaktuj się z nami',

    'cta' => [
        'start_trial' => 'Zacznij teraz',
        'contact' => 'Skontaktuj się',
        'contact_subject' => 'Hovera Transport Enterprise — zapytanie',
    ],

    'audience_hint' => [
        'default' => '—',
        'small_carriers' => 'Małe firmy i przewoźnicy indywidualni',
        'growing_carriers' => 'Rosnące firmy z większym taborem',
        'mid_large_carriers' => 'Średnie i większe firmy',
        'enterprise' => 'Powyżej 15 kierowców / 25 pojazdów',
    ],

    'feature' => [
        // Start
        'calculator_hgv' => 'Pełny kalkulator wycen z routingiem HGV (OpenRouteService)',
        'pdf_quotes_public_acceptance' => 'Oferty PDF + publiczna akceptacja klienta + dystrybucja WhatsApp/email',
        'crm_clients' => 'CRM klientów ze stawkami indywidualnymi',
        'poi_google_import' => 'POI: własne miejsca + import z Google Maps',
        'calendar_ical' => 'Kalendarz transportów + iCal feed (Google/Apple Calendar)',
        'public_page_pl' => 'Publiczna strona firmy (PL)',
        'payments_csv_import' => 'Wpłaty + koszty z importem CSV',
        'invoices_ksef' => 'Faktury (KSeF i inne formaty)',
        'reports_basic' => 'Raporty: kierowcy, klienci, pojazdy, cash-flow',
        'support_email_24h' => 'Wsparcie email · odpowiedź w 24h',

        // Pro (cumulative)
        'multilang_public_page' => 'Wielojęzyczna strona publiczna (PL + EN + DE)',
        'custom_rates_per_client' => 'Indywidualne stawki i minimalne kwoty per klient',
        'auto_toll_estimation' => 'Auto-szacowanie opłat drogowych (ORS tollways)',
        'stop_types_dictionary' => 'Słownik typów postoju (załadunek/rozładunek/weterynarz/nocowanie)',
        'public_gallery' => 'Galeria publiczna ze zdjęciami transportów',

        // Business (cumulative)
        'custom_branding' => 'Custom branding (logo + kolory na stronie publicznej i PDF-ach)',
        'advanced_reports' => 'Raporty zaawansowane: marże, top trasy, popularność tras',
        'export_csv_json_gdpr' => 'Eksport CSV/JSON wszystkich danych (RODO art. 20)',
        'configurable_toll_rates' => 'Konfigurowalne stawki opłat drogowych (lekki vs HGV)',
        'roadmap_priority' => 'Priorytet w roadmapie (głosowanie na features)',

        // Enterprise
        'dedicated_environment' => 'Dedykowane środowisko (osobny VPS)',
        'sla_financial_99_9' => 'SLA 99,9% z gwarancją finansową',
        'live_onboarding' => 'Onboarding z trenerem na żywo (2–4 h)',
        'data_migration_free' => 'Migracja danych — gratis',
        'white_label' => 'White-label (system pod marką klienta)',
        'api_rest' => 'API REST dla integracji',
        'dedicated_storage' => 'Backup do dedykowanego storage (S3 / GDrive)',
        'custom_integrations' => 'Custom integracje (CRM / ERP / księgowość)',
    ],

    'addons_heading' => 'Dodatki (add-ony)',
    'addons_sub' => 'Wszystkie dodatki dostępne globalnie — niezależnie od wybranego planu.',
    'addons_table' => [
        'name' => 'Dodatek',
        'type' => 'Rozliczenie',
        'price' => 'Cena',
    ],
    'addon_type' => [
        'one_time' => 'jednorazowo',
        'recurring_monthly' => 'co miesiąc',
    ],

    'nav' => [
        'stable_pricing' => 'Cennik dla stajni',
        'signup' => 'Załóż konto',
    ],
    'footer' => [
        'signup' => 'Załóż konto',
        'terms' => 'Regulamin',
    ],
];
