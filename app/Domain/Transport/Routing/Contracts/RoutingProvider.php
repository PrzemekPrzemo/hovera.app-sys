<?php

declare(strict_types=1);

namespace App\Domain\Transport\Routing\Contracts;

use App\Domain\Transport\Routing\Data\Coords;
use App\Domain\Transport\Routing\Data\Route;
use App\Domain\Transport\Routing\Data\RouteOptions;

interface RoutingProvider
{
    /**
     * Identyfikator providera (np. 'ors', 'mapbox', 'google').
     * Używany w cache i diagnostyce.
     */
    public function id(): string;

    /**
     * Oblicza trasę między dwoma punktami. Rzuca RoutingException w razie
     * błędu sieci/API/braku trasy. Nie cache'uje — caching robi RoutingService.
     */
    public function calculateRoute(Coords $from, Coords $to, RouteOptions $options): Route;
}
