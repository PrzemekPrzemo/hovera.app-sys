<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Tenant\BoardingService;
use App\Models\Tenant\Box;
use App\Models\Tenant\Instructor;
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

        // Lista instruktorów (opt-in przez settings.public_profile.show_instructors,
        // default false). Cache 10 min razem z box availability.
        $instructors = null;
        if ((bool) ($publicProfile['show_instructors'] ?? false)) {
            $instructors = Cache::remember(
                "public_instructors:{$slug}",
                now()->addMinutes(10),
                fn () => $this->loadInstructors($tenant),
            );
        }

        // Cennik (boarding services) — opt-in przez show_pricing
        $pricing = null;
        if ((bool) ($publicProfile['show_pricing'] ?? false)) {
            $pricing = Cache::remember(
                "public_pricing:{$slug}",
                now()->addMinutes(10),
                fn () => $this->loadPricing($tenant),
            );
        }

        return response()->view('public.tenant', [
            'tenant' => $tenant,
            'primary_color' => $branding['primary_color'] ?? '#A8956B',
            'logo_url' => $branding['logo_url'] ?? null,
            'hero_image_url' => $branding['hero_image_url'] ?? null,
            'tagline' => $publicProfile['tagline'] ?? 'Stajnia jeździecka',
            'description' => $publicProfile['description'] ?? null,
            'contact_email' => $publicProfile['email'] ?? null,
            'contact_phone' => $publicProfile['phone'] ?? null,
            'address' => $publicProfile['address'] ?? null,
            'website' => $publicProfile['website'] ?? null,
            'opening_hours' => $publicProfile['opening_hours'] ?? null,
            'social' => [
                'facebook' => $publicProfile['social_facebook'] ?? null,
                'instagram' => $publicProfile['social_instagram'] ?? null,
                'youtube' => $publicProfile['social_youtube'] ?? null,
                'tiktok' => $publicProfile['social_tiktok'] ?? null,
            ],
            'box_availability' => $boxAvailability,
            'instructors' => $instructors,
            'pricing' => $pricing,
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * @return array<int, array{name: string, price_pln: string, unit: string, frequency: string}>|null
     */
    private function loadPricing(Tenant $tenant): ?array
    {
        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }
        try {
            return BoardingService::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['name', 'price_cents', 'unit', 'frequency'])
                ->map(fn ($s) => [
                    'name' => $s->name,
                    'price_pln' => number_format($s->price_cents / 100, 2, ',', ' '),
                    'unit' => $s->unit ?? 'szt.',
                    'frequency' => match ((string) $s->frequency?->value) {
                        'monthly' => 'mies.',
                        'daily' => 'dzień',
                        'per_use' => 'raz',
                        'once' => 'jednorazowo',
                        default => '',
                    },
                ])
                ->all();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{name: string, color: string}>|null
     */
    private function loadInstructors(Tenant $tenant): ?array
    {
        if ($this->tenants->current()?->id !== $tenant->id) {
            $this->tenants->setCurrent($tenant);
        }
        try {
            return Instructor::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8)
                ->get(['name', 'color'])
                ->map(fn ($i) => ['name' => $i->name, 'color' => $i->color ?? '#A8956B'])
                ->all();
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * Embeddable widget — minimalistyczny render bez chrome (header/footer).
     * Stajnia wstawia jako <iframe> na swojej stronie WWW. Allow CORS bo
     * iframe może być na różnym originie.
     */
    public function embed(string $slug, string $widget): View|Response
    {
        $tenant = $this->resolveTenant($slug);
        if (! $tenant) {
            abort(404);
        }

        $branding = (array) ($tenant->branding ?? []);
        $publicProfile = (array) (($tenant->settings ?? [])['public_profile'] ?? []);

        $payload = [
            'tenant' => $tenant,
            'primary_color' => $branding['primary_color'] ?? '#A8956B',
            'logo_url' => $branding['logo_url'] ?? null,
            'tagline' => $publicProfile['tagline'] ?? null,
        ];

        $payload['box_availability'] = $widget === 'box-availability'
            ? Cache::remember("public_box_availability:{$slug}", now()->addMinutes(10), fn () => $this->computeBoxAvailability($tenant))
            : null;

        $payload['instructors'] = $widget === 'instructors'
            ? Cache::remember("public_instructors:{$slug}", now()->addMinutes(10), fn () => $this->loadInstructors($tenant))
            : null;

        $payload['pricing'] = $widget === 'pricing'
            ? Cache::remember("public_pricing:{$slug}", now()->addMinutes(10), fn () => $this->loadPricing($tenant))
            : null;

        return response()
            ->view("public.embed.{$widget}", $payload)
            ->header('Cache-Control', 'public, max-age=300')
            // Allow embedding na dowolnej stronie (stajnia wkleja iframe)
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', 'frame-ancestors *');
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
