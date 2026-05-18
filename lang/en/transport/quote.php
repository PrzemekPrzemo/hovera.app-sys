<?php

declare(strict_types=1);

return [
    'section' => [
        'header' => 'Header',
        'customer' => 'Customer',
        'route' => 'Route',
        'resources' => 'Resources (optional)',
        'pricing' => 'Pricing',
        'terms' => 'Terms & notes',
    ],

    'form' => [
        'label' => [
            'number' => 'Number',
            'status' => 'Status',
            'valid_until' => 'Valid until',
            'customer_name' => 'Full name',
            'customer_email' => 'Email',
            'customer_phone' => 'Phone',
            'customer_company' => 'Company',
            'customer_tax_id' => 'Tax ID / VAT',
            'customer_address' => 'Billing address',
            'pickup_address' => 'Pickup address',
            'dropoff_address' => 'Drop-off address',
            'preferred_date' => 'Date',
            'preferred_time' => 'Time',
            'round_trip' => 'Round trip',
            'loaded' => 'Loaded (with horse)',
            'vehicle' => 'Vehicle',
            'driver' => 'Driver',
            'distance_km' => 'Distance',
            'rate_per_km' => 'Rate',
            'duration_seconds' => 'Duration (s)',
            'base_cost' => 'Base cost',
            'fuel_surcharge' => 'Fuel surcharge',
            'minimum_adjustment' => 'Min. adjustment',
            'net_total' => 'Net',
            'vat_rate' => 'VAT rate',
            'vat_amount' => 'VAT amount',
            'gross_total' => 'Gross',
            'currency' => 'Currency',
            'routing_provider' => 'Route source',
            'terms' => 'Commercial terms',
            'notes' => 'Internal notes',
        ],
        'helper' => [
            'terms' => 'Visible to the customer on the quote / PDF.',
            'notes' => 'Team-only notes — not shared with customer.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Number',
            'customer' => 'Customer',
            'route' => 'Route',
            'preferred_date' => 'Date',
            'gross_total' => 'Gross',
            'status' => 'Status',
            'created_at' => 'Created',
        ],
    ],

    'action' => [
        'send' => 'Send to customer',
        'withdraw' => 'Withdraw quote',
    ],

    'notify' => [
        'sent' => 'Quote sent',
        'withdrawn' => 'Quote withdrawn',
    ],
];
