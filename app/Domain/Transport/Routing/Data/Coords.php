<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Data;

final readonly class Coords
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {}

    public function cacheKey(): string
    {
        // 5 miejsc po przecinku = ~1.1m dokładności na równiku — wystarczy
        // do deduplikacji adresu/trasy w cache.
        return sprintf('%.5f,%.5f', $this->lat, $this->lng);
    }
}
