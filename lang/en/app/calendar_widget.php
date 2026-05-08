<?php

declare(strict_types=1);

return [
    'action' => [
        'create' => [
            'label' => 'Add booking',
            'modal_heading' => 'New booking',
            'success' => 'Booking added',
            'conflict_title' => 'Conflict',
        ],
        'edit' => [
            'label' => 'Edit booking',
            'modal_heading' => 'Edit booking',
            'success' => 'Booking updated',
        ],
        'delete' => [
            'label' => 'Delete booking',
            'success' => 'Booking deleted',
        ],
    ],

    'form' => [
        'label' => [
            'type' => 'Type',
            'starts_at' => 'Starts',
            'ends_at' => 'Ends',
            'horse' => 'Horse',
            'instructor' => 'Instructor',
            'arena' => 'Arena',
            'client' => 'Client',
            'title' => 'Title',
            'status' => 'Status',
            'notes' => 'Notes',
        ],
    ],
];
