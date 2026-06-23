<?php

declare(strict_types=1);

return [
    'nav' => 'Messages — specialists',
    'model' => 'Specialist thread',
    'model_plural' => 'Specialist threads',
    'messages' => 'Messages',
    'unverified' => 'unverified',

    'form' => [
        'specialist' => 'Specialist',
        'specialist_hint' => 'Lists only specialists linked to your stable (invited via Hovera).',
        'horse' => 'Horse (optional)',
        'horse_placeholder' => '— general thread —',
        'subject' => 'Subject',
        'body' => 'Message body',
    ],

    'table' => [
        'subject' => 'Subject',
        'specialist' => 'Specialist',
        'last_message' => 'Last message',
    ],

    'action' => [
        'new' => 'New thread',
        'open' => 'Open',
        'reply' => 'Reply',
    ],

    'sender' => [
        'specialist' => 'Specialist',
        'stable' => 'Stable',
    ],

    'error' => [
        'no_context' => 'No stable context — refresh the page and try again.',
    ],
];
