<?php

declare(strict_types=1);

return [
    'navigation' => 'Favorite carriers',
    'title' => 'Favorite carriers',

    'intro' => [
        'title' => 'Favorite carriers',
        'body' => 'Mark up to :limit transport companies as favorites (currently :current). When submitting a transport inquiry, we pre-fill the direct list — you choose 1-3 to actually send the request to.',
    ],

    'search_placeholder' => 'Search by company name, tax ID, slug…',
    'empty' => 'No verified transport companies match the criteria.',

    'action' => [
        'add' => 'Add to favorites',
        'remove' => 'Remove',
    ],

    'notify' => [
        'added' => 'Added to favorites',
        'removed' => 'Removed from favorites',
        'limit_reached' => 'Favorites limit reached',
        'limit_body' => 'Maximum :limit favorites. Remove one first.',
        'error' => 'Error',
    ],
];
