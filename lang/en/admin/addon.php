<?php

declare(strict_types=1);

return [
    'form' => [
        'helper' => [
            'code' => 'Identifier (unique within the plan), e.g. horses_plus_10.',
            'name' => 'Marketing label, e.g. "+10 horses".',
            'resource_type' => 'The kind of limit/resource the add-on increases.',
            'quantity' => 'How much the limit increases (e.g. 10 for "+10 horses").',
            'sort_order' => 'Lower = higher in the list.',
        ],
        'label' => [
            'resource_type' => 'Resource type',
            'quantity' => 'Quantity',
            'price_monthly' => 'Monthly price',
            'price_yearly' => 'Yearly price',
            'is_active' => 'Active',
        ],
        'resource_types' => [
            'horses' => 'Horses',
            'users' => 'Users',
            'clients' => 'Clients',
            'storage_gb' => 'Storage (GB)',
            'custom' => 'Other',
        ],
    ],
    'table' => [
        'column' => [
            'resource_type' => 'Resource',
            'quantity' => 'Qty',
            'price_monthly_short' => 'Mo.',
            'price_yearly' => 'Yearly',
            'is_active_short' => 'Act.',
        ],
        'resource_types_short' => [
            'horses' => 'Horses',
            'users' => 'Users',
            'clients' => 'Clients',
            'storage_gb' => 'GB',
            'custom' => 'Other',
        ],
    ],
];
