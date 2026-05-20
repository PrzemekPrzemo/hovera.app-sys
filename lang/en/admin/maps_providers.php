<?php

declare(strict_types=1);

return [
    'navigation' => 'Maps & routing',
    'title' => 'Maps / routing / geocoding providers',

    'form' => [
        'section' => [
            'mapbox' => 'Mapbox',
            'mapbox_description' => 'Address geocoding + routing. Public key pk.eyJ... — issued by '
                .'mapbox.com/account/access-tokens (free tier: 100k req/month). Used by the transport '
                .'calculator (`/transport/calculator`) and public inquiry form.',
            'ors' => 'OpenRouteService (ORS)',
            'ors_description' => 'Open-source routing engine with OSRM backend. Free tier 2000 req/day. '
                .'API key from openrouteservice.org/dev/#/signup. Fallback when Mapbox out-of-quota.',
            'google' => 'Google Maps Routes API (Business+)',
            'google_description' => 'Premium routing — best ETA with real-time traffic. Requires billing '
                .'enabled in Google Cloud Console (console.cloud.google.com). Plan-aware: only Business+ '
                .'tenants can select. Expensive (~$5/1000 req).',
            'autocomplete' => 'Address autocomplete (typeahead)',
            'autocomplete_description' => 'Independent settings for the transporter panel and the public '
                .'inquiry form — e.g. cheap Photon for public traffic (where bots probe) and accurate '
                .'Mapbox in the panel (where quotes get built). Mapbox tokens are NOT exposed to the '
                .'browser — JS calls the proxy /api/transport/places/suggest.',
        ],
        'label' => [
            'mapbox_token' => 'Mapbox Access Token',
            'ors_api_key' => 'ORS API Key',
            'google_api_key' => 'Google Maps API Key',
            'autocomplete_provider_panel' => 'Transporter panel (Calculator + offers)',
            'autocomplete_provider_public' => 'Public form `/transport/zapytanie`',
        ],
        'helper' => [
            'mapbox_token' => 'Starts with `pk.eyJ1...`. Public token (read-only) is enough for MVP.',
            'ors_api_key' => 'Long alphanumeric — paste full value from the ORS dashboard.',
            'google_api_key' => 'Starts with `AIzaSy...`. Enable Routes API and set a billing alert.',
            'autocomplete_provider_panel' => 'Default: Mapbox (better PL addresses). If Mapbox is chosen but token is empty the app automatically falls back to Photon.',
            'autocomplete_provider_public' => 'Default: Photon (free, no token). Mapbox on the public form burns quota when bots probe.',
        ],
        'option' => [
            'autocomplete' => [
                'off' => 'Disabled (plain text input)',
                'photon' => 'Photon (komoot.io) — free, OSM',
                'mapbox' => 'Mapbox — accurate, uses your token',
            ],
        ],
    ],

    'action' => [
        'save_button' => 'Save configuration',
        'saved' => 'Maps providers configuration saved',
    ],
];
