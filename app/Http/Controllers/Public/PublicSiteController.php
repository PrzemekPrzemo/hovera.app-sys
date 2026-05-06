<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Publicly-reachable per-stable micro-site at /s/{slug}.
 *
 * - No auth, no tenant middleware (it's the public-facing front)
 * - Suspended / churned / soft-deleted tenants 404 (not "we have your
 *   data, just can't show it" — invisible)
 * - Resolution cached for 5 minutes; we accept short staleness on
 *   branding edits in exchange for a cheap public page
 */
class PublicSiteController extends Controller
{
    public function show(string $slug): View|Response
    {
        $tenant = $this->resolveTenant($slug);

        if (! $tenant) {
            abort(404);
        }

        $branding = (array) ($tenant->branding ?? []);
        $publicProfile = (array) (($tenant->settings ?? [])['public_profile'] ?? []);

        return response()->view('public.tenant', [
            'tenant' => $tenant,
            'primary_color' => $branding['primary_color'] ?? '#10b981',
            'logo_url' => $branding['logo_url'] ?? null,
            'description' => $publicProfile['description'] ?? null,
            'contact_email' => $publicProfile['email'] ?? null,
            'contact_phone' => $publicProfile['phone'] ?? null,
            'address' => $publicProfile['address'] ?? null,
            'website' => $publicProfile['website'] ?? null,
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    private function resolveTenant(string $slug): ?Tenant
    {
        // Slug character set is enforced at create time, but defence in
        // depth: reject anything that doesn't match here too.
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        return Cache::remember(
            "public_site:{$slug}",
            now()->addMinutes(5),
            fn () => Tenant::query()
                ->where('slug', $slug)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->first(),
        );
    }
}
