<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Type',
            'performed_at' => 'Procedure date',
            'summary' => 'Short description',
            'performed_by' => 'Performed by',
            'performed_by_placeholder' => 'e.g. assistant (if different from specialist)',
            'specialist' => 'Specialist',
            'specialist_placeholder' => '— pick from list —',
            'next_due_at' => 'Next due',
            'cost' => 'Cost',
            'details' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Date',
            'type' => 'Type',
            'summary' => 'Description',
            'performed_by' => 'By',
            'next_due_at' => 'Next due',
        ],
    ],
];
