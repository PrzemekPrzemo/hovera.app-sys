<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Time and type',
            'resources' => 'Resources',
            'details' => 'Details',
        ],
        'label' => [
            'type' => 'Type',
            'starts_at' => 'Starts',
            'ends_at' => 'Ends',
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'arena' => 'Arena',
            'client' => 'Client',
            'title' => 'Title (for events / blocks)',
            'status' => 'Status',
            'price' => 'Price',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Starts',
            'ends_at' => 'Ends',
            'type' => 'Type',
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'arena' => 'Arena',
            'client' => 'Client',
            'status' => 'Status',
        ],
        'filter' => [
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'upcoming' => 'Only upcoming',
        ],
    ],
];
