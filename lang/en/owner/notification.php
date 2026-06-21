<?php

declare(strict_types=1);

return [
    'navigation' => 'Notifications',

    'model' => [
        'singular' => 'notification',
        'plural' => 'Notifications',
    ],

    'column' => [
        'read' => 'Read',
        'title' => 'Subject',
        'body' => 'Body',
        'received_at' => 'Received',
    ],

    'filter' => [
        'unread' => 'Unread only',
    ],

    'action' => [
        'open' => 'Open',
        'mark_read' => 'Mark as read',
    ],

    'bulk' => [
        'mark_all_read' => 'Mark all as read',
    ],

    'fallback_title' => 'Notification',
];
