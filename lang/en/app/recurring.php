<?php

declare(strict_types=1);

return [
    'days_of_week' => [
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '0' => 'Sunday',
    ],

    'form' => [
        'section' => [
            'basic' => 'Basics',
            'recurrence' => 'Recurrence',
            'default_resources' => 'Default resources',
            'details' => 'Details',
        ],
        'label' => [
            'name' => 'Series name',
            'name_placeholder' => 'Mon school 17:00',
            'type' => 'Type',
            'starts_time' => 'Start time',
            'duration_minutes' => 'Duration (min)',
            'pattern' => 'Pattern',
            'interval' => 'Every',
            'days_of_week' => 'Days of week',
            'recurrence_starts_on' => 'From',
            'recurrence_ends_on' => 'Until (optional)',
            'max_occurrences' => 'Occurrence limit',
            'max_occurrences_placeholder' => 'e.g. 26',
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'arena' => 'Arena',
            'client' => 'Client',
            'title' => 'Session title',
            'price' => 'Price',
            'is_active' => 'Active series',
            'notes' => 'Notes',
        ],
        'helper' => [
            'interval' => '1 = every, 2 = every other…',
            'recurrence_ends_on' => 'Empty = no end; expander generates max 365 occurrences at once.',
            'max_occurrences' => 'Alternative to the end date.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Type',
            'pattern' => 'Pattern',
            'starts_time' => 'Time',
            'duration_minutes' => 'Min',
            'recurrence_starts_on' => 'From',
            'recurrence_ends_on' => 'Until',
            'recurrence_ends_on_empty' => '— no end —',
            'occurrences_count' => 'Occurrences',
            'is_active' => 'Active',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],

    'action' => [
        'expand' => [
            'label' => 'Generate occurrences',
            'success_title' => 'Series expanded',
            'success_body' => 'Created :count occurrences.',
            'skipped' => ' Skipped due to conflict: :list.',
        ],
        'cancel_series' => [
            'label' => 'Cancel series',
            'modal_heading' => 'Cancel the entire series',
            'modal_description' => 'Past occurrences are preserved, future ones cancelled.',
            'success_title' => 'Series cancelled',
            'success_body' => 'Cancelled :count future occurrences.',
        ],
    ],
];
