<?php

declare(strict_types=1);

return [
    'page' => [
        'title' => 'Weight & feeding',
        'breadcrumb' => 'Weight & feeding',
    ],

    'header' => [
        'label' => 'Stable',
    ],

    'access' => [
        'denied' => 'You do not have access to this data. Your horse must be actively boarded at a stable (or have a past boarding) for you to see weight and feeding plan.',
    ],

    'weight' => [
        'heading' => 'Body weight log',
        'latest_prefix' => 'Latest:',
        'empty' => 'The stable has not recorded any weight measurements yet. Entries will appear here as the stable logs them (typically once a month).',
        'col' => [
            'measured_at' => 'Date',
            'weight' => 'Weight',
            'delta' => 'Change',
            'girth' => 'Girth',
            'notes' => 'Notes',
        ],
    ],

    'feeding' => [
        'heading' => 'Feeding plan',
        'note' => 'Plan set by the stable — read-only.',
        'empty' => 'The stable has not set up a feeding plan for this horse yet.',
        'col' => [
            'meal' => 'Meal',
            'feed_type' => 'Feed',
            'amount' => 'Amount',
            'notes' => 'Notes',
        ],
    ],

    'meal' => [
        'breakfast' => 'Breakfast',
        'midday' => 'Midday',
        'evening' => 'Evening',
        'night' => 'Night',
    ],
];
