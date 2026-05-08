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
];
