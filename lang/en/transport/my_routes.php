<?php

declare(strict_types=1);

return [
    'navigation' => 'My routes',
    'model' => 'route',
    'model_plural' => 'my routes',

    'column' => [
        'date' => 'Date',
        'pickup' => 'Pickup',
        'dropoff' => 'Dropoff',
        'customer' => 'Customer',
        'phone' => 'Phone',
        'status' => 'Status',
    ],

    'empty' => [
        'heading' => 'No assigned routes',
        'description' => 'An operator will assign you routes once a customer accepts the quote. You will be notified by email.',
    ],
];
