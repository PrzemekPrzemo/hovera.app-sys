<?php

declare(strict_types=1);

return [
    'navigation' => 'Pricing & rates',
    'title' => 'Transport pricing & rates',

    'section' => [
        'rates' => 'Per-kilometre rates',
        'rates_description' => 'Base rates used to calculate quotes.',
        'fuel' => 'Fuel',
        'fuel_description' => 'Fuel surcharge: when the current diesel price exceeds the base, we add the difference × consumption.',
        'tax_currency' => 'Tax & currency',
        'routing' => 'Maps & routing provider',
        'routing_description' => 'OpenRouteService (free) covers 95% of cases. Google and Mapbox require your own API key.',
    ],

    'form' => [
        'label' => [
            'rate_per_km' => 'Rate per km',
            'rate_per_km_loaded' => 'Rate per km loaded',
            'minimum_charge' => 'Minimum charge per job',
            'fuel_consumption_l_per_100km' => 'Fuel consumption (L/100 km)',
            'fuel_surcharge_enabled' => 'Enable fuel surcharge',
            'fuel_base_price_pln' => 'Base diesel price',
            'vat_rate' => 'VAT rate',
            'currency' => 'Currency',
            'routing_provider' => 'Routing provider',
            'routing_api_key' => 'API key',
        ],
        'helper' => [
            'rate_per_km_loaded' => 'Leave empty if the same as unloaded.',
            'fuel_surcharge_enabled' => 'We add the difference between current and base price.',
            'routing_api_key' => 'API key for the selected provider. Stored securely in the database.',
        ],
        'option' => [
            'routing_provider' => [
                'ors' => 'OpenRouteService (free)',
                'mapbox' => 'Mapbox (your key)',
                'google' => 'Google Maps Routes (your key)',
            ],
        ],
    ],

    'action' => [
        'save' => 'Save settings',
        'saved' => 'Settings saved.',
    ],
];
