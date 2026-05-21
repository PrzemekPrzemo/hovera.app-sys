<?php

declare(strict_types=1);

return [
    'navigation' => 'Favorite carriers',
    'navigation_group' => 'Transport',

    'model' => [
        'singular' => 'Favorite carrier',
        'plural' => 'Favorite carriers',
    ],

    'form' => [
        'transporter' => 'Carrier',
        'transporter_helper' => 'List of verified carriers in the Hovera network. After adding you can send them a targeted request from "Order transport".',
        'notes' => 'Notes (optional)',
        'notes_placeholder' => 'e.g. "picks up Pegasus weekly for training" or "great reviews from stable X"',
    ],

    'table' => [
        'name' => 'Carrier',
        'slug' => 'Slug',
        'notes' => 'Notes',
        'added' => 'Added',
    ],

    'empty' => [
        'heading' => 'No favorite carriers yet',
        'description' => 'Add trusted carriers — when ordering transport you can pick "send only to favorites" instead of broadcasting to all.',
    ],

    'action' => [
        'add' => 'Add carrier',
    ],
];
