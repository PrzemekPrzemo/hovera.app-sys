<?php

declare(strict_types=1);

/**
 * Global CORS dla Laravel HandleCors middleware. Embed snippet API
 * (`/api/transport/inquiry`) **NIE** używa global CORS — robi per-tenant
 * whitelist przez `App\Http\Middleware\ResolveEmbedCors`, więc wykluczamy
 * jego path z global handler'a.
 *
 * Mobile API (`/api/v1/*`) używa Bearer tokenu (Sanctum) z `EnsureFrontendRequestsAreStateful`,
 * tam także CORS ograniczony do skonfigurowanych origin'ów.
 *
 * Patrz docs/TRANSPORT.md §16.
 */
return [
    // Globalny CORS aktywny tylko dla mobile API stack'a. Embed inquiry path
    // gateowany własnym per-tenant middleware'm i celowo nieobjęty tym handler'em.
    'paths' => ['api/v1/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
