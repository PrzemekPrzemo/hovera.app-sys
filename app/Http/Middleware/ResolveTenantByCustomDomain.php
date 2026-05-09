<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes a tenant's public micro-site under a custom domain (e.g.
 * mojastajnia.pl) to the existing /s/{slug}/* paths via internal
 * URI rewrite. The visitor's address bar keeps the vanity domain;
 * Laravel sees the request as if the slug prefix were already there.
 *
 * Scope: only the public micro-site / portal / booking. Admin panel
 * /app and master /admin remain on the central host (app.hovera.app);
 * they require a verified central session and shouldn't be exposed
 * via tenant vanity domains.
 *
 * Verification gate: only tenants with `custom_domain_verified_at`
 * set are routed. This prevents unfinished DNS setups (CNAME without
 * TXT confirmation) from bringing the panel up under a stranger's
 * domain.
 */
class ResolveTenantByCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // Skip the central app host — we recognise it by env URL or
        // the configured domain. Anything else might be a vanity
        // domain pointed at our infrastructure.
        $centralHost = strtolower(parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
        if ($host === '' || $host === $centralHost || $host === 'localhost' || $host === '127.0.0.1') {
            return $next($request);
        }

        $tenant = Tenant::query()
            ->where('custom_domain', $host)
            ->whereNotNull('custom_domain_verified_at')
            ->where('status', 'active')
            ->first();

        if (! $tenant) {
            return $next($request);
        }

        $this->rewritePublicPath($request, $tenant);

        return $next($request);
    }

    /**
     * Prepends `/s/{slug}` to the request path so the existing public
     * routes match. Admin / master / system endpoints reachable via
     * vanity domain are blocked (404) — they belong on central host.
     */
    private function rewritePublicPath(Request $request, Tenant $tenant): void
    {
        $prefix = (string) config('hovera.public_site.prefix', 's');
        $original = $request->getPathInfo();

        // Block admin and system paths — vanity domain isn't intended
        // to expose Filament panels or login flows.
        $blockedPrefixes = ['/app', '/admin', '/tenant', '/two-factor', '/impersonation', '/login', '/forgot-password', '/reset-password', '/invite', '/locale', '/demo'];
        foreach ($blockedPrefixes as $blocked) {
            if (str_starts_with($original, $blocked)) {
                abort(404);
            }
        }

        // Already prefixed — pass through unchanged.
        $rewritten = str_starts_with($original, "/{$prefix}/{$tenant->slug}")
            ? $original
            : "/{$prefix}/{$tenant->slug}".($original === '/' ? '' : $original);

        if ($rewritten === $original) {
            return;
        }

        // Rewrite both the routing path and the symfony request line so
        // route matching, URL helpers and downstream middleware see the
        // canonical /s/{slug}/... path.
        $request->server->set('REQUEST_URI', $rewritten.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        $request->server->set('PATH_INFO', $rewritten);

        $reflection = new \ReflectionObject($request);
        if ($reflection->hasProperty('pathInfo')) {
            $prop = $reflection->getProperty('pathInfo');
            $prop->setAccessible(true);
            $prop->setValue($request, $rewritten);
        }
        if ($reflection->hasProperty('requestUri')) {
            $prop = $reflection->getProperty('requestUri');
            $prop->setAccessible(true);
            $prop->setValue($request, $rewritten.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }
    }
}
