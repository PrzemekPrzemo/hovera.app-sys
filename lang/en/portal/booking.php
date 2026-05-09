<?php

declare(strict_types=1);

return [
    'title' => 'Book a lesson',
    'back' => '← Back to dashboard',
    'heading' => 'Book a lesson',
    'subtitle' => 'Pick your horse, instructor and slot · :tenant',
    'errors_heading' => 'Please review:',

    'no_horses' => 'You have no horses on this account. Please contact the stable.',
    'no_dates' => 'No available dates with this instructor in the near future.',
    'no_slots' => 'No free slots on this day. Pick another day.',

    'label' => [
        'horse' => 'Your horse',
        'horse_for' => 'Horse to ride',
        'instructor' => 'Instructor',
        'instructor_placeholder' => '— pick an instructor —',
        'day' => 'Day',
        'slot' => 'Time',
        'notes' => 'Notes (optional)',
        'notes_placeholder' => 'e.g. preferred arena / skill level',
    ],

    'actions' => [
        'submit' => 'Send booking request',
    ],

    'errors' => [
        'disabled' => 'Online booking is disabled for this stable.',
        'horse_invalid' => 'The selected horse does not belong to your account.',
        'instructor_invalid' => 'Instructor is unavailable.',
        'slot_taken' => 'This slot was just taken. Please pick another one.',
    ],

    'success_flash' => '✓ Booking request sent. The stable will confirm by e-mail.',
    'disabled_flash' => 'Online booking is disabled for this stable — please contact them by phone.',
];
