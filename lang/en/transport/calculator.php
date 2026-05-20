<?php

declare(strict_types=1);

return [
    'navigation' => 'Quote calculator',
    'title' => 'Transport quote calculator',

    'section' => [
        'route' => 'Route',
        'options' => 'Options',
    ],

    'form' => [
        'label' => [
            'from_address' => 'Pickup address',
            'to_address' => 'Drop-off address',
            'loaded' => 'Loaded (with horse)',
            'round_trip' => 'Round trip',
            'mode' => 'Calculation mode',
            'avoid_tolls' => 'Avoid toll roads',
            'avoid_ferries' => 'Avoid ferries',
            'profile' => 'Vehicle profile',
        ],
        'helper' => [
            'mode' => '"Return to home base" adds km from drop-off back to the transporter base. Requires a base set in Transport Settings — otherwise falls back to round trip.',
        ],
        'placeholder' => [
            'from_address' => 'e.g. Marymoncka 1 Stable, Warsaw',
            'to_address' => 'e.g. Sportowa 1, Olsztyn',
        ],
        'option' => [
            'profile' => [
                'truck' => 'Truck (HGV)',
                'car' => 'Car',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Calculate quote',
        'calculated' => 'Quote calculated.',
        'failed' => 'Could not calculate quote',
        'save_as_quote' => 'Save as quote',
    ],
    'notify' => [
        'lead_prefilled_title' => 'Inquiry data prefilled',
        'lead_prefilled_body' => 'Addresses and customer contact are already filled — click "Calculate" to compute the price.',
    ],

    'result' => [
        'heading' => 'Quote result',
        'from' => 'From',
        'to' => 'To',
        'distance' => 'Distance',
        'duration' => 'Travel time',
        'rate_used' => 'Rate applied',
        'base_cost' => 'Base cost',
        'fuel_surcharge' => 'Fuel surcharge',
        'minimum_adjustment' => 'Minimum-charge adjustment',
        'net_total' => 'Net total',
        'vat' => 'VAT (:rate%)',
        'gross_total' => 'Gross total',
        'routing_via' => 'Route calculated via: :provider',
    ],

    'error' => [
        'no_tenant' => 'No active tenant — please log in again.',
        'unknown' => 'Unexpected error. Please try again.',
    ],
];
