<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Komponent <x-route-map> — Leaflet renderer dla widoków wyceny.
 * Patrz docs/MARKETPLACE-ROADMAP.md "Calculator live UX (Leaflet)".
 *
 * Sprawdzamy że view się rendreuje, payload JSON jest valid, Leaflet
 * CDN jest dołączony, oraz że pusty stan (brak coords + brak polyline)
 * nie wyrzuca żadnego markup'u.
 */
class RouteMapComponentTest extends TestCase
{
    public function test_renders_map_with_polyline_and_markers(): void
    {
        $html = Blade::render(
            '<x-route-map :polyline="$p" :fromLat="$fl" :fromLng="$fg" :toLat="$tl" :toLng="$tg" />',
            ['p' => '_p~iF~ps|U_ulLnnqC', 'fl' => 52.2297, 'fg' => 21.0122, 'tl' => 50.0647, 'tg' => 19.945],
        );

        $this->assertStringContainsString('id="route-map-', $html);
        $this->assertStringContainsString('data-route-map', $html);
        $this->assertStringContainsString('data-route-payload', $html);
        // Polyline jako string w JSON payload — html-escape przez Blade.
        $this->assertStringContainsString('_p~iF~ps|U_ulLnnqC', $html);
        $this->assertStringContainsString('leaflet@1.9.4', $html);
    }

    public function test_renders_with_only_from_to_no_polyline_for_fallback(): void
    {
        // Bez polyline: komponent rysuje fallback dashed line między markerami.
        $html = Blade::render(
            '<x-route-map :fromLat="$fl" :fromLng="$fg" :toLat="$tl" :toLng="$tg" />',
            ['fl' => 52.0, 'fg' => 21.0, 'tl' => 50.0, 'tg' => 19.0],
        );

        $this->assertStringContainsString('data-route-map', $html);
        // Blade escape'uje cudzysłowy w html attribute → &quot;polyline&quot;:null.
        $this->assertStringContainsString('polyline&quot;:null', $html);
        $this->assertStringContainsString('from&quot;:[52', $html);
    }

    public function test_renders_nothing_when_no_data(): void
    {
        $html = Blade::render('<x-route-map />');

        // Empty render — nie wypluwamy ani div'a ani skryptu.
        $this->assertStringNotContainsString('data-route-map', $html);
        $this->assertStringNotContainsString('leaflet', $html);
    }

    public function test_renders_waypoints_in_payload(): void
    {
        $html = Blade::render(
            '<x-route-map :fromLat="$fl" :fromLng="$fg" :toLat="$tl" :toLng="$tg" :waypoints="$w" />',
            [
                'fl' => 52.0, 'fg' => 21.0,
                'tl' => 50.0, 'tg' => 19.0,
                'w' => [
                    ['lat' => 51.5, 'lng' => 20.5, 'label' => 'Pośrednia'],
                ],
            ],
        );

        // Waypoint label trafia do JSON (escape przez html attribute escape).
        $this->assertStringContainsString('Pośrednia', $html);
        $this->assertStringContainsString('51.5', $html);
    }

    public function test_custom_height_and_map_id(): void
    {
        $html = Blade::render(
            '<x-route-map :polyline="$p" :fromLat="$fl" :fromLng="$fg" :toLat="$tl" :toLng="$tg" height="480px" mapId="custom-id" />',
            ['p' => 'POLY', 'fl' => 52.0, 'fg' => 21.0, 'tl' => 50.0, 'tg' => 19.0],
        );

        $this->assertStringContainsString('id="custom-id"', $html);
        $this->assertStringContainsString('height: 480px', $html);
    }

    public function test_polyline_string_is_escaped_for_xss(): void
    {
        // Defensive: polyline jest user-derived (z ORS) — sprawdzamy
        // że potencjalny markup w stringu nie wycieka jako HTML.
        $html = Blade::render(
            '<x-route-map :polyline="$p" :fromLat="$fl" :fromLng="$fg" :toLat="$tl" :toLng="$tg" />',
            ['p' => '</script><img src=x onerror=alert(1)>', 'fl' => 52.0, 'fg' => 21.0, 'tl' => 50.0, 'tg' => 19.0],
        );

        // Surowy <script> z input'u nie powinien być w output'cie literalnie
        // jako tag — JSON escape + html attribute escape gwarantuje to.
        $this->assertStringNotContainsString('<img src=x', $html);
    }
}
