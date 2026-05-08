<?php

declare(strict_types=1);

return [
    'title' => 'My bookings — :tenant',
    'subtitle' => 'Client portal · :tenant',
    'logout' => 'Sign out',

    'flash' => [
        'reschedule_success' => '✓ Booking rescheduled. We sent a confirmation by email.',
    ],

    'sections' => [
        'upcoming' => 'Upcoming bookings',
        'passes' => 'Your passes',
        'history' => 'History',
        'unpaid_invoices' => 'Invoices to pay',
        'messages' => 'Messages',
        'horses' => 'Your horses',
    ],

    'empty' => [
        'upcoming' => 'No upcoming bookings.',
        'history' => 'No booking history.',
    ],

    'duration_min' => ':minutes min',
    'instructor_label' => 'Instructor: :name',
    'horse_label' => 'Horse: :name',

    'status' => [
        'requested' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ],

    'actions' => [
        'reschedule' => 'Reschedule',
        'cancel' => 'Cancel',
        'view_all' => 'View all →',
    ],

    'pass' => [
        'remaining' => ':remaining / :total remaining',
        'valid_until' => 'valid until :date',
        'recent_uses' => 'Recently used',
        'lesson_label' => 'Lesson :date',
    ],

    'invoice' => [
        'issued_at' => 'Issued: :date',
        'due_at' => 'Due: :date',
    ],

    'horse' => [
        'years_short' => 'yr',
        'overdue_pill' => ':count overdue',
        'upcoming_pill' => ':count in 30 days',
        'ok_pill' => 'OK',
    ],

    'unread_messages' => [
        'one' => '📬 :count new message',
        'other' => '📬 :count new messages',
    ],
];
