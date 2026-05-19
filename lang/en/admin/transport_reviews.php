<?php

declare(strict_types=1);

return [
    'navigation' => 'Transporter reviews',
    'model' => [
        'singular' => 'Review',
        'plural' => 'Marketplace reviews',
    ],
    'table' => [
        'column' => [
            'transporter' => 'Transporter',
            'rating' => 'Rating',
            'customer' => 'Customer',
            'comment' => 'Comment',
            'status' => 'Status',
            'flagged_at' => 'Flagged',
        ],
    ],
    'filter' => [
        'rating' => 'Rating',
        'transporter' => 'Transporter',
        'flagged' => 'Flagged by transporter',
    ],
    'action' => [
        'publish' => 'Publish',
        'hide' => 'Hide',
        'reject' => 'Reject (delete)',
    ],
    'bulk' => [
        'publish' => 'Publish selected',
        'publish_done' => 'Published :count reviews',
        'hide' => 'Hide selected',
        'hide_done' => 'Hidden :count reviews',
    ],
    'form' => [
        'moderation_notes' => 'Moderation notes',
    ],
    'notify' => [
        'moderated' => 'Review updated (status: :status).',
        'rejected' => 'Review rejected and deleted.',
    ],
    'view' => [
        'section_review' => 'Review',
        'section_moderation' => 'Moderation',
    ],
];
