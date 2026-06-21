<?php

declare(strict_types=1);

return [
    'navigation' => 'Powiadomienia',

    'model' => [
        'singular' => 'powiadomienie',
        'plural' => 'Powiadomienia',
    ],

    'column' => [
        'read' => 'Przecz.',
        'title' => 'Temat',
        'body' => 'Treść',
        'received_at' => 'Otrzymano',
    ],

    'filter' => [
        'unread' => 'Tylko nieprzeczytane',
    ],

    'action' => [
        'open' => 'Otwórz',
        'mark_read' => 'Oznacz jako przeczytane',
    ],

    'bulk' => [
        'mark_all_read' => 'Oznacz wszystkie jako przeczytane',
    ],

    'fallback_title' => 'Powiadomienie',
];
