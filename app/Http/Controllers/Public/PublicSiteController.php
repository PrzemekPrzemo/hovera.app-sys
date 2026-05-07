<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Tenant\Box;
use App\Tenancy\TenantManager;
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
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    public function show(string $slug): View|Response
    {
        $tenant = $this->resolveTenant($slug);

        if (! $tenant) {
            abort(404);
        }

        $branding = (array) ($tenant->branding ?? []);
        $publicProfile = (array) (($tenant->settings ?? [])['public_profile'] ?? []);

        // "Mamy X wolnych boxów" — opt-in przez settings.public_profile.show_box_availability
        // (default true). Wynik cachowany 10 min żeby nie hit'ować tenant DB
        // co request publicznej strony.
        $boxAvailability = null;
        $showBoxAvailability = (bool) ($publicProfile['show_box_availability'] ?? true);
        if ($showBoxAvailability) {
            $boxAvailability = Cache::remember(
                "public_box_availability:{$slug}",
                now()->addMinutes(10),
                fn () => $this->computeBoxAvailability($tenant),
            );
        }

        return response()->view('public.tenant', [
            'tenant' => $tenant,
            'primary_color' => $branding['primary_color'] ?? '#10b981',
            'logo_url' => $branding['logo_url'] ?? null,
            'description' => $publicProfile['description'] ?? null,
            'contact_email' => $publicProfile['email'] ?? null,
            'contact_phone' => $publicProfile['phone'] ?? null,
            'address' => $publicProfile['address'] ?? null,
            'website' => $publicProfile['website'] ?? null,
            'box_availability' => $boxAvailability,
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * @return array{free:int, total:int}|null
     */
    private function computeBoxAvailability(Tenant $tenant): ?array
    {
        // Switch do tenant DB na ten compute (z auto-restore w finally
        // w setCurrent — PublicSite renderuje pojedynczą stronę, brak
        // potrzeby żeby tenant context wyciekł).
        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }

        try {
            $boxes = Box::query()->where('is_active', true)->withCount('horses')->get();
        } catch (\Throwable) {
            // Tabela boxes może jeszcze nie istnieć (stare migracje) — wtedy
            // ukryj widget zamiast crashować publiczną stronę.
            return null;
        }

        if ($boxes->isEmpty()) {
            return null;
        }

        $total = (int) $boxes->sum('capacity');
        $occupied = (int) $boxes->sum(fn ($b) => min($b->horses_count, $b->capacity));
        $free = max(0, $total - $occupied);

        return ['free' => $free, 'total' => $total];
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
