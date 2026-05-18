<?php

declare(strict_types=1);

return [
    'navigation' => 'Cennik i stawki',
    'title' => 'Cennik i stawki transportu',

    'section' => [
        'rates' => 'Stawki za kilometr',
        'rates_description' => 'Stawki podstawowe — używane do wyliczania ofert.',
        'fuel' => 'Paliwo',
        'fuel_description' => 'Dopłata paliwowa: gdy aktualna cena ON przekracza cenę bazową, system dolicza różnicę × zużycie.',
        'tax_currency' => 'Podatki i waluta',
        'routing' => 'Dostawca map i tras',
        'routing_description' => 'OpenRouteService (darmowy) wystarcza w 95% przypadków. Google i Mapbox wymagają własnego klucza API.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Stawka za km',
            'rate_per_km_loaded' => 'Stawka za km z koniem',
            'minimum_charge' => 'Minimalna opłata zlecenia',
            'fuel_consumption_l_per_100km' => 'Spalanie (L/100 km)',
            'fuel_surcharge_enabled' => 'Włącz dopłatę paliwową',
            'fuel_base_price_pln' => 'Cena bazowa ON',
            'vat_rate' => 'Stawka VAT',
            'currency' => 'Waluta',
            'routing_provider' => 'Dostawca tras',
            'routing_api_key' => 'Klucz API',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Pozostaw puste jeśli taka sama jak bez koni.',
            'fuel_surcharge_enabled' => 'Doliczamy różnicę pomiędzy ceną aktualną a bazową.',
            'routing_api_key' => 'Klucz API dla wybranego dostawcy. Przechowujemy bezpiecznie w bazie.',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (darmowy)',
                'mapbox' => 'Mapbox (własny klucz)',
                'google' => 'Google Maps Routes (własny klucz)',
            ],
        ],
    ],

    'action' => [
        'save' => 'Zapisz ustawienia',
        'saved' => 'Ustawienia zapisane.',
    ],
];
