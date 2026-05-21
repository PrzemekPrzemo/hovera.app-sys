<?php

declare(strict_types=1);

return [
    'title' => 'Horse activity timeline',
    'breadcrumb' => 'Timeline',
    'summary' => 'Showing :count entries (most recent first, up to 200).',

    'filter' => [
        'heading' => 'Filters',
        'kinds' => 'Categories',
        'from' => 'From date',
        'to' => 'To date',
    ],

    'action' => [
        'apply' => 'Apply',
        'reset' => 'Reset filters',
    ],

    'empty' => [
        'heading' => 'No entries',
        'description' => 'The stable has not recorded any activity for this horse in the selected range.',
    ],

    'kind' => [
        'health' => 'Health',
        'box' => 'Box',
        'weight' => 'Weight',
        'activity' => 'Activity',
        'photo' => 'Photo',
        'document' => 'Document',
    ],

    'subkind' => [
        'health' => [
            'vet_visit' => 'vet visit',
            'vaccination' => 'vaccination',
            'deworming' => 'deworming',
            'dentist' => 'dentist',
            'farrier' => 'farrier',
            'check_up' => 'check-up',
            'medication' => 'medication',
            'other' => 'other',
        ],
        'box' => [
            'assigned' => 'moved in',
            'vacated' => 'moved out',
        ],
        'weight' => [
            'measured' => 'weight measured',
        ],
        'activity' => [
            'feeding' => 'feeding',
            'grooming' => 'grooming',
            'turnout' => 'paddock',
            'exercise' => 'training / lunging',
            'box_cleaning' => 'box cleaning',
            'transport_event' => 'competition trip',
            'other' => 'other',
        ],
        'photo' => [
            'added' => 'new photo',
        ],
        'document' => [
            'passport' => 'passport',
            'contract' => 'contract',
            'insurance' => 'insurance',
            'vaccine_book' => 'vaccination card',
            'ownership_proof' => 'ownership proof',
            'competition_licence' => 'competition licence',
            'vet_certificate' => 'vet certificate',
            'other' => 'other document',
        ],
    ],

    'actor' => [
        'stable' => 'Stable',
        'owner' => 'Owner',
        'system' => 'System',
    ],

    'next_due_at' => 'Next due',
    'view_link' => 'Timeline',
];
