<?php

declare(strict_types=1);

return [
    'vat_rates' => [
        '23' => '23%',
        '8' => '8%',
        '5' => '5%',
        '0' => '0%',
        'zw' => 'exempt',
        'np' => 'N/A',
    ],

    'form' => [
        'section' => [
            'service' => 'Pricing item',
            'service_description' => 'Pick these per horse ("Boarding" tab on the horse card). They appear in the client portal — the owner sees what they pay for.',
        ],
        'label' => [
            'name' => 'Name',
            'name_placeholder' => 'e.g. Hay, Box cleaning, Transport to events',
            'description' => 'Description (optional)',
            'unit' => 'Unit',
            'unit_placeholder' => 'pcs / kg / hr / month',
            'frequency' => 'Billing frequency',
            'price_net' => 'Net price',
            'vat_rate' => 'VAT rate',
            'is_active' => 'Active',
            'sort_order' => 'Order',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'frequency' => 'Frequency',
            'price_net' => 'Net price',
            'vat' => 'VAT',
            'horses_count' => 'Horses',
            'is_active' => 'Active',
        ],
    ],
];
