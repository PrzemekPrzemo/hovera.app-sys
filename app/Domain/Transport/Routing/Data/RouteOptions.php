<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Data;

/**
 * Opcje routingu. `profile` mapowane per-provider:
 *   - ORS:    'driving-hgv'  → ciężarowy (uwzględnia restrykcje wagi/wysokości)
 *             'driving-car'  → osobowy
 *   - Mapbox: 'driving'      → standardowy samochodowy (brak natywnego HGV)
 *             'driving-traffic' → z live traffic
 *   - Google: 'TRUCK'        → routingPreference truck (HGV-aware)
 *             'DRIVE'        → standardowy samochodowy
 *
 * RoutingProvider dokonuje własnego mapowania z generic 'truck' / 'car' / 'fast'.
 *
 * Restrykcje wagowe/wysokościowe (`weightTons`, `heightMeters`) używane
 * tylko przez ORS HGV profile (profile_params.restrictions). Mapbox /
 * Google ignorują — ich API nie ma analogicznego param. Patrz
 * docs/MARKETPLACE-ROADMAP.md "ORS routing z weight/height pojazdu".
 */
final readonly class RouteOptions
{
    public function __construct(
        public string $profile = 'truck',
        public bool $avoidTolls = false,
        public bool $avoidFerries = false,
        public ?float $weightTons = null,
        public ?float $heightMeters = null,
    ) {}
}
