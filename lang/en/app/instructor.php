<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Instructor data',
        ],
        'label' => [
            'name' => 'Full name',
            'phone' => 'Phone',
            'hourly_rate' => 'Hourly rate',
            'color' => 'Calendar color',
            'is_active' => 'Active',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Full name',
            'phone' => 'Phone',
            'hourly_rate' => 'Rate',
            'color' => 'Color',
            'is_active' => 'Active',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],

    'actions' => [
        'ics_url' => 'Calendar feed (.ics)',
    ],
    'ics_modal' => [
        'heading' => 'Calendar feed for :name',
        'description' => 'Copy the URL and paste it into Google Calendar / Outlook / Apple Calendar as "Add calendar from URL". Lessons sync automatically every few hours.',
        'url_label' => 'Feed URL (subscription)',
        'howto' => 'Google Calendar → "Other calendars" → "+ → From URL" → paste URL. Outlook → "Add calendar → Subscribe from web". Apple → File → New Calendar Subscription.',
        'token_ensured' => 'URL ready',
        'close' => 'Close',
    ],
];
