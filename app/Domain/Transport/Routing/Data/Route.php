<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Data;

final readonly class Route
{
    public function __construct(
        public float $distanceKm,
        public int $durationSeconds,
        public ?string $polyline = null,         // encoded polyline (Google format)
        public string $providerId = 'unknown',    // 'ors' / 'mapbox' / 'google'
    ) {}

    public function durationHours(): float
    {
        return $this->durationSeconds / 3600;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'distance_km' => $this->distanceKm,
            'duration_seconds' => $this->durationSeconds,
            'polyline' => $this->polyline,
            'provider_id' => $this->providerId,
        ];
    }
}
