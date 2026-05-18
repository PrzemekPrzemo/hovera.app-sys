<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use App\Models\Central\TransportServiceArea;
use App\Models\Tenant\Vehicle;
use App\Tenancy\TenantManager;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Publiczny profil transportera pod /t/{slug}. Marketing landing — pokazuje
 * firmę, flotę i obsługiwane województwa z CTA do /transport/zapytanie.
 *
 * - Bez auth — strona publiczna, indeksowalna (SEO play dla transporterów).
 * - 404 dla niezweryfikowanych / Stable / suspended / soft-deleted tenantów —
 *   nie powinni mieć publicznej obecności dopóki Hovera nie potwierdzi
 *   dokumentów (patrz docs/TRANSPORT.md verification flow).
 * - Cachowane 5 min na poziomie slug→tenant; flota/voivodeships dłużej (10 min).
 * - Flota leci przez TenantManager::setCurrent + try/catch — jeśli per-tenant DB
 *   się nie podłączy (np. w testach feature bez tenant migracji), sekcja
 *   floty po prostu znika zamiast crashować całą stronę.
 */
class TransporterProfileController extends Controller
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

        $vehicles = Cache::remember(
            "public_transporter_vehicles:{$slug}",
            now()->addMinutes(10),
            fn () => $this->loadVehicles($tenant),
        );

        $serviceAreas = Cache::remember(
            "public_transporter_service_areas:{$slug}",
            now()->addMinutes(10),
            fn () => $this->loadServiceAreas($tenant),
        );

        return response()->view('public.transport.profile', [
            'tenant' => $tenant,
            'primary_color' => $branding['primary_color'] ?? '#A8956B',
            'logo_url' => $branding['logo_url'] ?? null,
            'hero_image_url' => $branding['hero_image_url'] ?? null,
            'tagline' => $publicProfile['tagline'] ?? null,
            'description' => $publicProfile['description'] ?? null,
            'contact_email' => $publicProfile['email'] ?? null,
            'contact_phone' => $publicProfile['phone'] ?? null,
            'address' => $publicProfile['address'] ?? null,
            'website' => $publicProfile['website'] ?? null,
            'vehicles' => $vehicles,
            'service_areas' => $serviceAreas,
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * @return array{primary: list<string>, adjacent: list<string>}
     */
    private function loadServiceAreas(Tenant $tenant): array
    {
        $own = TransportServiceArea::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->pluck('voivodeship')
            ->all();

        $adjacency = (array) config('transport.voivodeship_adjacency', []);
        $adjacent = [];
        foreach ($own as $voivodeship) {
            foreach ((array) ($adjacency[$voivodeship] ?? []) as $neighbour) {
                if (! in_array($neighbour, $own, true)) {
                    $adjacent[] = $neighbour;
                }
            }
        }

        return [
            'primary' => $own,
            'adjacent' => array_values(array_unique($adjacent)),
        ];
    }

    /**
     * @return list<array{
     *   name:string, capacity:?int, year:?int,
     *   has_air_suspension:bool, has_camera:bool, has_climate_control:bool,
     *   photo:?string
     * }>
     */
    private function loadVehicles(Tenant $tenant): array
    {
        if ($this->tenants->current()?->id !== $tenant->id) {
            try {
                $this->tenants->setCurrent($tenant);
            } catch (\Throwable) {
                return [];
            }
        }

        try {
            return Vehicle::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->limit(12)
                ->get([
                    'name', 'capacity_horses', 'year_of_manufacture',
                    'has_air_suspension', 'has_camera', 'has_climate_control',
                    'photos',
                ])
                ->map(fn (Vehicle $v) => [
                    'name' => (string) $v->name,
                    'capacity' => $v->capacity_horses,
                    'year' => $v->year_of_manufacture,
                    'has_air_suspension' => (bool) $v->has_air_suspension,
                    'has_camera' => (bool) $v->has_camera,
                    'has_climate_control' => (bool) $v->has_climate_control,
                    'photo' => is_array($v->photos) && ! empty($v->photos)
                        ? (string) ($v->photos[0]['url'] ?? $v->photos[0] ?? '')
                        : null,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveTenant(string $slug): ?Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        return Cache::remember(
            "public_transporter:{$slug}",
            now()->addMinutes(5),
            function () use ($slug) {
                $tenant = Tenant::query()
                    ->where('slug', $slug)
                    ->whereIn('status', ['trialing', 'active', 'past_due'])
                    ->first();

                if (! $tenant || ! $tenant->isVerifiedTransporter()) {
                    return null;
                }

                return $tenant;
            },
        );
    }
}
