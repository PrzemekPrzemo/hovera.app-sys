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
            'type' => 'Type',
            'performed_at' => 'Procedure date',
            'performed_by' => 'Performed by (vet / farrier / company)',
            'summary' => 'Short description',
            'summary_placeholder' => 'Tetanus + flu vaccination',
            'next_due_at' => 'Next due',
            'cost' => 'Cost',
            'details' => 'Notes / medications / recommendations',
        ],
        'helper' => [
            'next_due_at' => 'This triggers a dashboard alert closer to the due date.',
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
