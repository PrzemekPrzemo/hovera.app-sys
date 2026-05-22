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
            'forbidden_title' => 'Not allowed to edit this booking',
            'forbidden_body' => 'As an employee you can only edit your own entries. Ask an instructor or manager to make the change.',
        ],
        'delete' => [
            'label' => 'Delete booking',
            'success' => 'Booking deleted',
            'forbidden_title' => 'Not allowed to delete this booking',
            'forbidden_body' => 'As an employee you can only delete your own entries.',
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
