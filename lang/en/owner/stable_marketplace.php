<?php

declare(strict_types=1);

return [
    'navigation' => 'Stables',
    'navigation_group' => 'Horses',
    'title' => 'Stables — find boarding for your horse',
    'intro' => 'Active stables in the Hovera network. Pick one to send your horse to — the registered stable receives a notification and accepts or declines the request.',

    'empty' => [
        'heading' => 'No stables available',
        'description' => 'No stable is currently accepting new horses in the Hovera network. Check back later.',
    ],

    'action' => [
        'request_boarding' => 'Send boarding request',
        'modal_heading' => 'Send horse to ":stable"',
        'horse_label' => 'Pick a horse',
        'horse_helper' => 'List of horses from your central registry. If a horse is missing — add it first in the "My horses" panel.',
        'no_passport' => 'no passport',
        'stable_missing' => 'The selected stable is unavailable. Refresh the page.',
        'horse_missing' => 'The selected horse is not yours or has been deleted.',
        'success' => 'Request sent',
        'success_body' => 'Stable ":stable" can now see your boarding request for ":horse" and was notified. Awaiting their acceptance.',
    ],
];
