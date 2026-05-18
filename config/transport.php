<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Routing providers
    |--------------------------------------------------------------------------
    |
    | Klucze API dla zewnętrznych dostawców routingu. Per-tenant override
    | trzyma się w `transport_settings.routing_provider.api_key` (per-tenant DB).
    | Plan-aware selection — patrz docs/TRANSPORT.md §7.
    */

    'providers' => [
        'ors' => [
            'api_key' => env('TRANSPORT_ORS_KEY', ''),
            'timeout' => env('TRANSPORT_ORS_TIMEOUT', 15),
        ],
        'mapbox' => [
            'access_token' => env('TRANSPORT_MAPBOX_TOKEN', ''),
            'timeout' => env('TRANSPORT_MAPBOX_TIMEOUT', 15),
        ],
        'google' => [
            'api_key' => env('TRANSPORT_GOOGLE_ROUTES_KEY', ''),
            'timeout' => env('TRANSPORT_GOOGLE_TIMEOUT', 15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route cache
    |--------------------------------------------------------------------------
    | Cache obliczonych tras w `route_cache` (central DB). Trasa Warszawa→Poznań
    | nie zmienia się z dnia na dzień — TTL 30 dni zbija koszty Google API.
    */

    'cache' => [
        'route_ttl_days' => env('TRANSPORT_ROUTE_CACHE_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fuel prices
    |--------------------------------------------------------------------------
    | FuelPriceService priorytetyzuje: per-tenant manual override →
    | najnowszy snapshot z central fuel_prices (TTL) → fallback hard-coded.
    | Scraper e-petrol.pl uruchamiamy `php artisan transport:scrape-fuel`
    | przez cron raz dziennie (06:00).
    */

    'fuel' => [
        'epetrol' => [
            'url' => env('TRANSPORT_EPETROL_URL', 'https://www.e-petrol.pl/'),
        ],
        'snapshot_max_age_days' => env('TRANSPORT_FUEL_SNAPSHOT_MAX_AGE_DAYS', 7),
        'fallback_price' => env('TRANSPORT_FUEL_FALLBACK_PRICE', 7.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification documents
    |--------------------------------------------------------------------------
    | Storage dla dokumentów weryfikacyjnych transportera (KRS, świadectwo
    | transportu zwierząt, OCP/OCS, dowód rejestracyjny). Default disk
    | 'local' (storage/app/transporter-docs/{tenant_id}/...) — na produkcji
    | warto wskazać S3-compatible bucket z restricted ACL.
    */

    'documents' => [
        'disk' => env('TRANSPORT_DOCUMENTS_DISK', 'local'),
        'max_size_mb' => env('TRANSPORT_DOCUMENTS_MAX_SIZE_MB', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Favorites
    |--------------------------------------------------------------------------
    | Max ulubionych transporterów per stajnia. OP3 z planu. Domyślnie 5 —
    | wystarczy żeby pokryć typowy wybór, mało żeby user nie produkował
    | listy o niskiej istotności decyzyjnej.
    */

    'favorites' => [
        'limit' => env('TRANSPORT_FAVORITES_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voivodeship adjacency
    |--------------------------------------------------------------------------
    | Routing leadów w trybie BROADCAST: dispatcher wybiera transporterów z
    | service_area pasującym do voivodeship leadu LUB sąsiednim. Patrz §5.4.
    | Konsumowane przez `LeadDispatcher` (faza 6).
    */

    'voivodeship_adjacency' => [
        'dolnośląskie' => ['lubuskie', 'wielkopolskie', 'opolskie'],
        'kujawsko-pomorskie' => ['pomorskie', 'warmińsko-mazurskie', 'mazowieckie', 'łódzkie', 'wielkopolskie'],
        'lubelskie' => ['podkarpackie', 'świętokrzyskie', 'mazowieckie', 'podlaskie'],
        'lubuskie' => ['zachodniopomorskie', 'wielkopolskie', 'dolnośląskie'],
        'łódzkie' => ['mazowieckie', 'świętokrzyskie', 'śląskie', 'opolskie', 'wielkopolskie', 'kujawsko-pomorskie'],
        'małopolskie' => ['świętokrzyskie', 'podkarpackie', 'śląskie'],
        'mazowieckie' => ['łódzkie', 'kujawsko-pomorskie', 'warmińsko-mazurskie', 'podlaskie', 'lubelskie', 'świętokrzyskie'],
        'opolskie' => ['śląskie', 'łódzkie', 'wielkopolskie', 'dolnośląskie'],
        'podkarpackie' => ['lubelskie', 'świętokrzyskie', 'małopolskie'],
        'podlaskie' => ['warmińsko-mazurskie', 'mazowieckie', 'lubelskie'],
        'pomorskie' => ['zachodniopomorskie', 'wielkopolskie', 'kujawsko-pomorskie', 'warmińsko-mazurskie'],
        'śląskie' => ['opolskie', 'łódzkie', 'świętokrzyskie', 'małopolskie'],
        'świętokrzyskie' => ['łódzkie', 'mazowieckie', 'lubelskie', 'podkarpackie', 'małopolskie', 'śląskie'],
        'warmińsko-mazurskie' => ['pomorskie', 'kujawsko-pomorskie', 'mazowieckie', 'podlaskie'],
        'wielkopolskie' => ['zachodniopomorskie', 'lubuskie', 'dolnośląskie', 'opolskie', 'łódzkie', 'kujawsko-pomorskie', 'pomorskie'],
        'zachodniopomorskie' => ['pomorskie', 'wielkopolskie', 'lubuskie'],
    ],

];
