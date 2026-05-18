<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    | Centralna definicja brand assets — używana w mailach, public sites,
    | landing page'ach. Filament panele mają własne `brandLogo()` /
    | `brandName()` (patrz Providers/Filament/*PanelProvider).
    */

    'brand' => [
        'name' => 'hovera',
        'tagline' => 'Clear. Calm. Confident.',
        'description' => 'European SaaS for stable and equestrian business management',
        'logo' => '/img/brand/hovera-logo.svg',
        'icon' => '/img/brand/hovera-icon.svg',
        'favicon' => '/favicon.svg',
        'colors' => [
            // Wybrane z brand book — używaj tych nazw zamiast hardcode'owanych hex
            'deep_brown' => '#3D2E22',  // primary dark / sidebar / heading
            'ochre' => '#A8956B',       // accent / CTA / link
            'cream' => '#F7F4EF',       // background light
            'sand' => '#E9E2D3',        // soft surface
            'taupe' => '#C8B8A4',       // muted accent
            'stone' => '#8F8576',       // secondary text
            'charcoal' => '#1F1A17',    // emphatic text
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Master admin
    |--------------------------------------------------------------------------
    */

    'admin' => [
        'path' => env('HOVERA_ADMIN_PATH', 'admin'),
        'require_2fa' => env('HOVERA_ADMIN_REQUIRE_2FA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public micro-site
    |--------------------------------------------------------------------------
    | Path prefix for the publicly-reachable per-stable site:
    |   https://app.hovera.app/{prefix}/{slug}
    */

    'public_site' => [
        'prefix' => env('HOVERA_PUBLIC_SITE_PREFIX', 's'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant provisioning
    |--------------------------------------------------------------------------
    | Naming conventions for the per-tenant MySQL database and user. The slug
    | is sanitised (lowercase, only [a-z0-9_]) before being concatenated.
    */

    'tenant' => [
        'db_prefix' => env('HOVERA_TENANT_DB_PREFIX', 'hovera_t_'),
        'user_prefix' => env('HOVERA_TENANT_USER_PREFIX', 'hovera_t_'),
        'db_host' => env('HOVERA_TENANT_DB_HOST', '127.0.0.1'),
        'db_port' => (int) env('HOVERA_TENANT_DB_PORT', 3306),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    'audit' => [
        // Actions that should never be logged (avoid log spam from heartbeat
        // endpoints etc.). Match against the action string.
        'ignore_actions' => [
            'admin.heartbeat',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    | Master admin impersonation is time-boxed. After `max_minutes` from
    | the start, the EnforceImpersonationExpiry middleware ends the session
    | automatically and returns the master admin to /admin.
    */

    'impersonation' => [
        'max_minutes' => (int) env('HOVERA_IMPERSONATION_MAX_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo
    |--------------------------------------------------------------------------
    | Public demo tenant — odwiedzający `/demo` lądują tu z rolą owner.
    | Resetowane co noc o 22:00 przez `hovera:demo:reset` (routes/console.php).
    | Provisioning jednorazowy przez `hovera:demo:seed --slug=demo`.
    */

    'demo' => [
        'slug' => env('HOVERA_DEMO_SLUG', 'demo'),
        'enabled' => (bool) env('HOVERA_DEMO_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal
    |--------------------------------------------------------------------------
    | Dane spółki + wersja regulaminu zaszywane w stopkach legal pages,
    | regulaminie marketplace transportowego, oraz w atrybucie
    | terms_version zapisywanym przy signupie.
    |
    | UWAGA: domyślne wartości są PRODUKCYJNE i pochodzą z istniejącego
    | regulaminu (Sendormeco Holding sp. z o.o.). Jeżeli operator
    | platformy zmieni się (np. dedykowana spółka Hovera Sp. z o.o.) —
    | nadpisać przez .env przed GA / publikacją regulaminu marketplace.
    */

    'legal' => [
        'company_name' => env('HOVERA_LEGAL_COMPANY_NAME', 'Sendormeco Holding sp. z o.o.'),
        'company_short' => env('HOVERA_LEGAL_COMPANY_SHORT', 'Sendormeco Holding'),
        'nip' => env('HOVERA_LEGAL_NIP', '5252866457'),
        'regon' => env('HOVERA_LEGAL_REGON', '389194801'),
        'krs' => env('HOVERA_LEGAL_KRS', '0000906110'),
        'address' => env('HOVERA_LEGAL_ADDRESS', 'ul. Złota 75A/7, 00-819 Warszawa'),
        'court' => env('HOVERA_LEGAL_COURT', 'Sąd Rejonowy dla m.st. Warszawy w Warszawie, XII Wydział Gospodarczy KRS'),
        'support_email' => env('HOVERA_LEGAL_SUPPORT_EMAIL', 'office@hovera.app'),
        'privacy_email' => env('HOVERA_LEGAL_PRIVACY_EMAIL', 'office@hovera.app'),
        'effective_date' => env('HOVERA_LEGAL_EFFECTIVE_DATE', '2026-05-18'),

        // Wersja regulaminu zapisywana przy signupie do tenants.terms_version.
        // Przy każdej istotnej zmianie regulaminu inkrementuj (format YYYY-MM)
        // — to pozwala wykryć ktorych tenantów trzeba ponownie poprosić o akceptację.
        'terms_version' => env('HOVERA_LEGAL_TERMS_VERSION', '2026-05'),
    ],
];
