<?php

declare(strict_types=1);

return [
    'navigation' => 'Box inquiries',
    'model' => 'Box inquiry',
    'model_plural' => 'Box inquiries',

    'section' => [
        'inquiry' => 'Inquiry details',
        'response' => 'Handling',
    ],

    'field' => [
        'name' => 'Full name',
        'email' => 'Email',
        'phone' => 'Phone',
        'horse_count' => 'Horse count',
        'preferred_from' => 'Preferred start',
        'message' => 'Message',
        'source' => 'Source',
        'status' => 'Status',
        'responded_at' => 'Responded at',
        'response_notes' => 'Response notes',
        'created_at' => 'Received',
    ],

    'status' => [
        'new' => 'New',
        'contacted' => 'Contacted',
        'closed' => 'Closed',
        'spam' => 'Spam',
    ],
];
