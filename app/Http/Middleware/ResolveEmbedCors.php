<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-tenant CORS resolver dla embed snippet API. Każdy transporter trzyma
 * własną whitelist'ę dozwolonych origin'ów w `tenants.embed_allowed_origins`
 * (central DB — nie ma potrzeby switching tenant connection w CORS gate).
 *
 *   1. Czyta `Origin` header.
 *   2. Resolve'uje transportera po `transporter_slug` (body/query).
 *   3. Sprawdza `isEmbedOriginAllowed($origin)`.
 *   4. Allowed → set CORS headers (`Access-Control-Allow-Origin`,
 *      `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`).
 *      Disallowed → brak CORS headers (browser zablokuje response).
 *
 * Patrz docs/TRANSPORT.md §16.
 */
class ResolveEmbedCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $slug = $this->extractTransporterSlug($request);

        $tenant = $slug !== '' ? $this->resolveTenant($slug) : null;

        $allowed = false;
        if ($tenant !== null && $origin !== '') {
            $allowed = $tenant->isEmbedOriginAllowed($origin);
        }

        // Preflight OPTIONS — odpowiadamy zawsze, CORS headers tylko jeśli
        // origin dopuszczony. Inaczej browser zablokuje następny POST.
        if ($request->getMethod() === 'OPTIONS') {
            return $this->buildPreflightResponse($origin, $allowed);
        }

        $response = $next($request);

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private function extractTransporterSlug(Request $request): string
    {
        $slug = (string) $request->input('transporter_slug', '');
        if ($slug === '') {
            $slug = (string) $request->query('transporter_slug', '');
        }

        // Defense in depth — slug pasuje do subdomain-safe regex.
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return '';
        }

        return $slug;
    }

    private function resolveTenant(string $slug): ?Tenant
    {
        return Tenant::query()
            ->where('slug', $slug)
            ->where('type', TenantType::Transporter)
            ->where('verification_status', VerificationStatus::Verified)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->first();
    }

    private function buildPreflightResponse(string $origin, bool $allowed): Response
    {
        $response = response()->noContent(204);

        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Hovera-Embed-Token');
            $response->headers->set('Access-Control-Max-Age', '3600');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
