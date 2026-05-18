<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing;

use App\Domain\Transport\Routing\Contracts\RoutingProvider;
use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\Route;
use App\Domain\Transport\Routing\Data\RouteOptions;
use App\Domain\Transport\Routing\Exceptions\RoutingException;
use App\Domain\Transport\Routing\Providers\GoogleMapsProvider;
use App\Domain\Transport\Routing\Providers\MapboxProvider;
use App\Domain\Transport\Routing\Providers\OpenRouteServiceProvider;
use App\Models\Central\RouteCache;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportSettings;

/**
 * Wybiera właściwy RoutingProvider dla tenant'a (plan-aware) i cache'uje
 * wyniki. Patrz docs/TRANSPORT.md §7.3 + §7.4.
 *
 * Flow:
 *   1. Odczytaj transport_settings.routing_provider z tenant DB
 *   2. Walidacja: plan tenanta zezwala na ten provider (plan.limits.routing_providers)
 *   3. Instancja provider'a z klucze (per-tenant override LUB env default)
 *   4. Cache lookup (sha1 z params)
 *   5. Provider call, zapis do cache, return
 */
class RoutingService
{
    public function __construct(
        private readonly OpenRouteServiceProvider $ors,
        private readonly MapboxProvider $mapbox,
        private readonly GoogleMapsProvider $google,
    ) {}

    public function calculate(Tenant $tenant, Coords $from, Coords $to, ?RouteOptions $options = null): Route
    {
        $options ??= new RouteOptions();
        $provider = $this->for($tenant);

        $cacheKey = $this->buildCacheKey($provider->id(), $options->profile, $from, $to);
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $route = $provider->calculateRoute($from, $to, $options);
        $this->writeCache($cacheKey, $from, $to, $options, $route);

        return $route;
    }

    /**
     * Resolved provider dla tenanta — używane też w testach do izolacji
     * konkretnego providera.
     */
    public function for(Tenant $tenant): RoutingProvider
    {
        $settings = TransportSettings::current();
        $config = $settings->routing_provider ?? ['provider' => 'ors'];
        $providerId = (string) ($config['provider'] ?? 'ors');
        $tenantKey = isset($config['api_key']) ? (string) $config['api_key'] : null;

        $this->assertPlanAllows($tenant, $providerId);

        return match ($providerId) {
            'mapbox' => $tenantKey !== null && $tenantKey !== '' ? $this->mapbox->withKey($tenantKey) : $this->mapbox,
            'google' => $tenantKey !== null && $tenantKey !== '' ? $this->google->withKey($tenantKey) : $this->google,
            default => $tenantKey !== null && $tenantKey !== '' ? $this->ors->withKey($tenantKey) : $this->ors,
        };
    }

    private function assertPlanAllows(Tenant $tenant, string $providerId): void
    {
        $allowed = (array) ($tenant->plan?->limits['routing_providers'] ?? ['ors']);
        if (! in_array($providerId, $allowed, true)) {
            throw RoutingException::planForbidden($providerId, (string) ($tenant->plan?->code ?? 'none'));
        }
    }

    private function buildCacheKey(string $providerId, string $profile, Coords $from, Coords $to): string
    {
        return sha1($providerId.':'.$profile.':'.$from->cacheKey().':'.$to->cacheKey());
    }

    private function readCache(string $cacheKey): ?Route
    {
        $row = RouteCache::query()
            ->where('cache_key', $cacheKey)
            ->where('expires_at', '>', now())
            ->first();

        if (! $row) {
            return null;
        }

        return new Route(
            distanceKm: (float) $row->distance_km,
            durationSeconds: (int) $row->duration_seconds,
            polyline: $row->polyline,
            providerId: $row->provider_id,
        );
    }

    private function writeCache(string $cacheKey, Coords $from, Coords $to, RouteOptions $options, Route $route): void
    {
        $ttlDays = (int) config('transport.cache.route_ttl_days', 30);

        RouteCache::query()->updateOrCreate(
            ['cache_key' => $cacheKey],
            [
                'provider_id' => $route->providerId,
                'profile' => $options->profile,
                'from_lat' => $from->lat,
                'from_lng' => $from->lng,
                'to_lat' => $to->lat,
                'to_lng' => $to->lng,
                'distance_km' => $route->distanceKm,
                'duration_seconds' => $route->durationSeconds,
                'polyline' => $route->polyline,
                'expires_at' => now()->addDays($ttlDays),
            ],
        );
    }
}
