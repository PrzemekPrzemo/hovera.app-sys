<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Transport\Geocoding\Autocomplete\PlacesAutocompleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Proxy endpoint dla address autocomplete'u. JS w przeglądarce (Filament
 * Calculator/Quote oraz publiczny `/transport/zapytanie`) bije tu zamiast
 * bezpośrednio w Mapbox API — tokeny Mapbox NIE są eksponowane do klienta.
 *
 *   GET /api/transport/places/suggest?q=war&context=panel|public
 *
 * Response:
 *   { "provider": "mapbox|photon", "items": [ {label, lat, lng, provider}, ... ] }
 *
 * Throttling: 60 req/min/IP (route-level). Min query length: 3 chars (Photon
 * spamuje wynikami dla 1-2 char queries i payloady puchną).
 */
class PlacesAutocompleteController extends Controller
{
    public function suggest(Request $request, PlacesAutocompleteService $service): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 3) {
            return response()->json(['provider' => null, 'items' => []]);
        }

        $context = $request->query('context') === 'public' ? 'public' : 'panel';
        if (! $service->isEnabledFor($context)) {
            return response()->json(['provider' => 'off', 'items' => []]);
        }

        $items = $service->suggest($context, $query, 'pl', 6);

        return response()->json([
            'provider' => $service->providerNameFor($context),
            'items' => array_map(static fn ($s) => $s->toArray(), $items),
        ]);
    }
}
