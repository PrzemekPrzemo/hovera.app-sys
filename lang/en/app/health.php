<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'entry' => 'Entry',
            'details' => 'Details',
        ],
        'label' => [
            'horse' => 'Horse',
            'horse_identification' => 'Horse identification',
            'template' => 'Treatment template',
            'template_placeholder' => '— optionally pick a template —',
            'type' => 'Type',
            'performed_at' => 'Procedure date',
            'performed_by' => 'Performed by (vet / farrier / company)',
            'performed_by_placeholder' => 'e.g. farrier assistant (if different from selected above)',
            'specialist' => 'Specialist',
            'specialist_placeholder' => '— pick from specialist list —',
            'summary' => 'Short description',
            'summary_placeholder' => 'Tetanus + flu vaccination',
            'next_due_at' => 'Next due',
            'cost' => 'Cost',
            'details' => 'Notes / medications / recommendations',
        ],
        'helper' => [
            'template' => 'Picking a template fills in type, summary and the suggested next-due date.',
            'next_due_at' => 'This triggers a dashboard alert closer to the due date.',
            'specialist' => 'List filtered by entry type — farriers for "Farrier", vets for others. Manage the list in Stable → Specialists.',
        ],
        'horse_identification' => [
            'microchip' => 'Microchip',
            'passport' => 'Passport number',
            'ueln' => 'UELN',
            'empty_warning' => 'No identification data for this horse. Fill in microchip / passport on the horse profile before treatment.',
            'missing' => 'Selected horse no longer exists (record deleted).',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Date',
            'horse' => 'Horse',
            'type' => 'Type',
            'summary' => 'Description',
            'performed_by' => 'By',
            'next_due_at' => 'Next due',
            'cost' => 'Cost',
        ],
        'filter' => [
            'horse' => 'Horse',
            'overdue' => 'Overdue (next due in past)',
            'due_30' => 'Due within 30 days',
        ],
    ],
];
