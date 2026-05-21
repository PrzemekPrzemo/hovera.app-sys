{{-- Komponent: mapa Leaflet z trasą wyceny transportu.

     Patrz docs/MARKETPLACE-ROADMAP.md "Calculator live UX (Leaflet map z
     geometrią)" — pierwsza faza, read-only render polyline'a + markerów
     start/end. Live recalc + debounced refresh przyjdą w kolejnych PR-ach.

     Atrybuty:
       - polyline (string|null)  — encoded Google polyline (z Quotation
                                    lub Quote.polyline). Gdy null → fallback
                                    do prostego markera/dwóch markerów bez linii.
       - fromLat / fromLng (float) — koordynaty pickup
       - toLat / toLng (float)     — koordynaty dropoff
       - waypoints (array)         — [{ lat, lng, label }] dodatkowych
                                       postojów; pokazywane jako numerowane
                                       markery między start a end.
       - height (string)           — CSS height (default 320px).
       - mapId (string)            — id elementu (auto-generowany gdy brak).

     Implementacja:
       - Leaflet z CDN (1.9.4, MIT licence)
       - OpenStreetMap tiles (free, atrybucja w view)
       - Polyline decoder inline (Google algorithm, ~30 linii JS)
       - Marker start zielony, end czerwony, waypoint pomarańczowy
       - fitBounds() po dodaniu wszystkich layer'ów
       - color-scheme: light hard-coded — mapy są zawsze jasne, zgodne
         z resztą /transport/* views.
--}}
@props([
    'polyline' => null,
    'fromLat' => null,
    'fromLng' => null,
    'toLat' => null,
    'toLng' => null,
    'waypoints' => [],
    'height' => '320px',
    'mapId' => null,
])

@php
    $mapId ??= 'route-map-'.uniqid();
    $fromLat = $fromLat !== null ? (float) $fromLat : null;
    $fromLng = $fromLng !== null ? (float) $fromLng : null;
    $toLat = $toLat !== null ? (float) $toLat : null;
    $toLng = $toLng !== null ? (float) $toLng : null;
    // Render skipped gdy brak żadnych koordynatów do pokazania.
    $hasData = ($fromLat && $fromLng) || ($toLat && $toLng) || ! empty($waypoints);
    // Dane do JS — escape jako JSON, żeby uniknąć XSS przy adresach z
    // user-typed danymi.
    $payload = json_encode([
        'polyline' => $polyline,
        'from' => $fromLat && $fromLng ? [$fromLat, $fromLng] : null,
        'to' => $toLat && $toLng ? [$toLat, $toLng] : null,
        'waypoints' => array_map(static fn ($w) => [
            'lat' => (float) ($w['lat'] ?? 0),
            'lng' => (float) ($w['lng'] ?? 0),
            'label' => (string) ($w['label'] ?? ''),
        ], $waypoints),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

@if ($hasData)
    {{-- Leaflet CSS/JS — ładowane raz per page nawet gdy komponent jest
         render'owany wielokrotnie (przeglądarka deduplikuje). --}}
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin="anonymous" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin="anonymous"></script>
    @endonce

    <div id="{{ $mapId }}"
         style="height: {{ $height }}; border-radius: 12px; overflow: hidden; color-scheme: light;"
         data-route-map
         data-route-payload="{{ $payload }}"></div>

    <script>
        (function () {
            'use strict';

            const init = () => {
                const el = document.getElementById({!! json_encode($mapId) !!});
                if (!el || el.dataset.initialized === '1') return;
                if (typeof L === 'undefined') {
                    // Skrypt Leaflet jeszcze nie załadowany — retry za chwilę.
                    setTimeout(init, 50);
                    return;
                }
                el.dataset.initialized = '1';

                let payload;
                try {
                    payload = JSON.parse(el.dataset.routePayload);
                } catch (e) {
                    console.warn('route-map: invalid payload', e);
                    return;
                }

                // Google polyline decoder — algorithm z dokumentacji Google
                // Maps. Zwraca [[lat, lng], ...] gotowe do Leaflet.polyline.
                const decode = (encoded) => {
                    if (!encoded) return [];
                    let i = 0, lat = 0, lng = 0, out = [];
                    while (i < encoded.length) {
                        let result = 1, shift = 0, b;
                        do {
                            b = encoded.charCodeAt(i++) - 63 - 1;
                            result += b << shift;
                            shift += 5;
                        } while (b >= 0x1f);
                        lat += (result & 1) ? ~(result >> 1) : (result >> 1);
                        result = 1; shift = 0;
                        do {
                            b = encoded.charCodeAt(i++) - 63 - 1;
                            result += b << shift;
                            shift += 5;
                        } while (b >= 0x1f);
                        lng += (result & 1) ? ~(result >> 1) : (result >> 1);
                        out.push([lat * 1e-5, lng * 1e-5]);
                    }
                    return out;
                };

                const map = L.map(el, {
                    zoomControl: true,
                    scrollWheelZoom: false,  // anti UX-hijack na scrollu strony
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                }).addTo(map);

                const bounds = [];
                const greenIcon = L.divIcon({
                    className: 'route-map-marker',
                    html: '<div style="background:#16a34a;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3);">A</div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12],
                });
                const redIcon = L.divIcon({
                    className: 'route-map-marker',
                    html: '<div style="background:#dc2626;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3);">B</div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12],
                });
                const waypointIcon = (index) => L.divIcon({
                    className: 'route-map-marker',
                    html: '<div style="background:#f59e0b;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3);">' + (index + 1) + '</div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                });

                if (payload.from) {
                    L.marker(payload.from, {icon: greenIcon}).addTo(map);
                    bounds.push(payload.from);
                }
                if (payload.to) {
                    L.marker(payload.to, {icon: redIcon}).addTo(map);
                    bounds.push(payload.to);
                }
                (payload.waypoints || []).forEach((wp, idx) => {
                    const marker = L.marker([wp.lat, wp.lng], {icon: waypointIcon(idx)}).addTo(map);
                    if (wp.label) marker.bindPopup(wp.label);
                    bounds.push([wp.lat, wp.lng]);
                });

                const points = decode(payload.polyline);
                if (points.length > 0) {
                    L.polyline(points, {color: '#A8956B', weight: 4, opacity: 0.8}).addTo(map);
                    points.forEach(p => bounds.push(p));
                } else if (payload.from && payload.to) {
                    // Brak polyline — narysuj prostą linię from→waypoints→to
                    // jako fallback wizualny.
                    const fallback = [payload.from, ...(payload.waypoints || []).map(w => [w.lat, w.lng]), payload.to];
                    L.polyline(fallback, {color: '#A8956B', weight: 3, opacity: 0.5, dashArray: '6 6'}).addTo(map);
                }

                if (bounds.length > 0) {
                    map.fitBounds(bounds, {padding: [30, 30], maxZoom: 14});
                }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endif
