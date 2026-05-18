<?php

declare(strict_types=1);

return [
    'navigation' => 'Inquiries',

    'section' => [
        'customer' => 'Customer',
        'route' => 'Route',
        'cargo' => 'Cargo',
        'lifecycle' => 'Lifecycle',
    ],

    'label' => [
        'name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'from' => 'From',
        'to' => 'To',
        'pickup_voivodeship' => 'Voivodeship (pickup)',
        'dropoff_voivodeship' => 'Voivodeship (drop-off)',
        'preferred_date' => 'Date',
        'preferred_time' => 'Time',
        'horse_count' => 'Horses',
        'flexible_date' => 'Date flexible',
        'notes' => 'Customer notes',
        'status' => 'Status',
        'mode' => 'Mode',
        'expires_at' => 'Expires',
    ],

    'table' => [
        'column' => [
            'customer' => 'Customer',
            'route' => 'Route',
            'preferred_date' => 'Date',
            'horse_count' => 'Horses',
            'status' => 'Status',
            'expires_at' => 'Expires',
            'created_at' => 'Received',
        ],
    ],

    'action' => [
        'respond' => 'Respond with offer',
    ],

    'notify' => [
        'respond_started' => 'Offer form opened',
        'respond_started_body' => 'Fill in details and send. The customer will see your offer next to other responses.',
    ],
];
