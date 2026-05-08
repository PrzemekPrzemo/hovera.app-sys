<?php

declare(strict_types=1);

return [
    'directions' => [
        'from_stable' => 'Stable → Client',
        'from_client' => 'Client → Stable',
    ],

    'form' => [
        'label' => [
            'subject' => 'Subject (optional)',
            'body' => 'Body',
            'attachments' => 'Attachments (max 5, up to 10 MB each)',
        ],
    ],

    'table' => [
        'column' => [
            'sent_at' => 'Sent',
            'direction' => 'Direction',
            'subject' => 'Subject',
            'body' => 'Preview',
            'attachments_short' => 'Att.',
            'read_short' => 'Read',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Write to owner',
            'failed' => 'Failed to send',
            'sent' => 'Message sent',
        ],
        'mark_read' => [
            'label' => 'Mark as read',
            'success' => 'Marked as read',
        ],
    ],
];
