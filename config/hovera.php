<?php

declare(strict_types=1);

return [

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
];
