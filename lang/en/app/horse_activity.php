<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Type',
            'performed_at' => 'When',
            'performed_by' => 'Performed by (groom name)',
            'performed_by_placeholder' => 'e.g. Tom (if different from selected specialist)',
            'specialist' => 'Specialist (farrier / vet)',
            'specialist_placeholder' => '— if performed by a specialist, pick from list —',
            'cost' => 'Extra cost (optional)',
            'summary' => 'Short description',
            'summary_placeholder' => 'e.g. "Turnout 9:00-12:00, east paddock"',
            'details' => 'Notes',
        ],
        'helper' => [
            'cost' => 'Fill in only if the activity incurred a cost beyond the flat fee (e.g. extra hay, transport).',
            'specialist' => 'List of all active specialists (farriers + vets). Configure in Stable → Specialists.',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Date',
            'type' => 'Type',
            'summary' => 'Description',
            'performed_by' => 'By',
            'cost' => 'Cost',
        ],
    ],
];
