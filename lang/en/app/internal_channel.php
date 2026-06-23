<?php

declare(strict_types=1);

return [
    'nav' => 'Team channels',
    'model' => 'Channel',
    'model_plural' => 'Team channels',
    'messages' => 'Messages',

    'form' => [
        'name' => 'Channel name',
        'description' => 'Description',
        'message' => 'Message',
        'message_hint' => 'You can mention someone with @name (e.g. @anna).',
    ],

    'table' => [
        'name' => 'Channel',
        'description' => 'Description',
        'default' => 'Default',
        'members' => 'Members',
    ],

    'action' => [
        'new' => 'New channel',
        'open' => 'Open',
        'post' => 'Post',
    ],
];
