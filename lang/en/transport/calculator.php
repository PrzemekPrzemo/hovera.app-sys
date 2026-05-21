<?php

declare(strict_types=1);

return [
    'navigation' => 'Quote calculator',
    'title' => 'Transport quote calculator',

    'section' => [
        'route' => 'Route',
        'options' => 'Options',
        'extra_costs' => 'Extra fees and margin',
        'extra_costs_description' => 'Fixed fees (tolls, ferry, etc.) and percent margin for this quote. Empty = defaults from Transport Settings.',
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
            'horses_count' => 'Number of horses',
            'fixed_fees' => 'Fixed fees (tolls, ferry, etc.)',
            'fixed_fees_name' => 'Name',
            'fixed_fees_amount' => 'Amount',
            'surcharge_percent' => 'Margin %',
        ],
        'helper' => [
            'mode' => '"Return to home base" adds km from drop-off back to the transporter base. Requires a base set in Transport Settings — otherwise falls back to round trip.',
            'horses_count' => 'Surcharge applies from the second horse onwards, per the rate in Transport Settings.',
            'fixed_fees' => 'Each item is added to the quote. Leave empty to use defaults from Transport Settings.',
            'surcharge_percent' => 'Percent margin added to costs (after minimum-charge adjustment, before VAT). Empty = default from Settings. 0 = no margin.',
        ],
        'action' => [
            'add_fixed_fee' => 'Add fee',
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
        'save_as_quote_inline' => 'Save & open editor',
        'saved_as_quote_inline_title' => 'Quote created',
        'saved_as_quote_inline_body' => 'Quote :number has been saved. Fill in the customer and contract data to send it.',
        'saved_as_quote_inline_placeholder_customer' => '(to fill in)',
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
        'extra_horse_fee' => 'Extra horses: :count × :rate :currency',
        'surcharge' => 'Margin (:percent%)',
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

    'live' => [
        'title' => 'Live preview',
        'hint' => 'Updates automatically as you edit',
        'loading' => 'Calculating…',
        'missing' => 'Enter addresses to see live price preview',
        'error' => 'Could not refresh the preview.',
        'currency_fallback' => 'PLN',
        'extra_horses' => 'Extra horses (:count)',
        'surcharge' => 'Margin (:percent%)',
        'vat' => 'VAT (:rate%)',
        'expand' => 'Show details',
        'collapse' => 'Hide details',
    ],
];
