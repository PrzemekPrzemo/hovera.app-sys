<?php

declare(strict_types=1);

return [
    'navigation' => 'Mapy i routing',
    'title' => 'Konfiguracja providerów map / routing / geocoding',

    'form' => [
        'section' => [
            'mapbox' => 'Mapbox',
            'mapbox_description' => 'Geocoding adresów + routing. Klucz publiczny pk.eyJ... — wydaje '
                .'mapbox.com/account/access-tokens (free tier: 100k req/mc). Używany przez kalkulator '
                .'transport (`/transport/calculator`) i formularz zapytań publicznych.',
            'ors' => 'OpenRouteService (ORS)',
            'ors_description' => 'Open-source routing engine z backendem OSRM. Free tier 2000 req/dzień. '
                .'Klucz API z openrouteservice.org/dev/#/signup. Fallback gdy Mapbox out-of-quota.',
            'google' => 'Google Maps Routes API (Business+)',
            'google_description' => 'Premium routing — najlepsze ETA z real-time traffic. Wymaga billing '
                .'enabled na konsoli Google Cloud (console.cloud.google.com). Plan-aware: tylko plany '
                .'Business+ tenantów mogą wybrać. Drogi (~$5/1000 req).',
        ],
        'label' => [
            'mapbox_token' => 'Mapbox Access Token',
            'ors_api_key' => 'ORS API Key',
            'google_api_key' => 'Google Maps API Key',
        ],
        'helper' => [
            'mapbox_token' => 'Zaczyna się od `pk.eyJ1...`. Public token (read-only) wystarczy dla MVP.',
            'ors_api_key' => 'Długi alphanumeric — wklej całość z dashboard ORS.',
            'google_api_key' => 'Zaczyna się od `AIzaSy...`. Włącz Routes API i ustaw billing alert.',
        ],
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację',
        'saved' => 'Konfiguracja providerów map zapisana',
    ],
];
