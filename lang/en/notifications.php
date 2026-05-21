<?php

declare(strict_types=1);

return [
    'common' => [
        'greeting' => 'Hello!',
        'greeting_named' => 'Hello :name!',
        'salutation_prefix' => '— ',
        'field' => [
            'term' => 'When',
            'instructor' => 'Instructor',
            'horse' => 'Horse',
            'arena' => 'Arena',
            'address' => 'Address',
            'phone' => 'Stable phone',
            'old_date' => 'Old date',
            'new_date' => 'New date',
            'cancelled_term' => 'Cancelled slot',
            'from' => 'From',
            'subject' => 'Subject',
            'issued_at' => 'Issue date',
            'gross_amount' => 'Gross amount',
            'due_date' => 'Due date',
            'client' => 'Client',
            'client_note' => 'Client note',
        ],
        'duration_minutes' => ':minutes min',
        'cancel_action' => 'Cancel booking',
        'cancel_policy' => 'If you need to cancel, click below. Cancelling at least :hours hours before the lesson is free of charge.',
        'portal_link' => 'You can find all your bookings in the client portal: [:url](:url)',
    ],

    'booking_confirmed' => [
        'subject' => 'Booking confirmed — :tenant',
        'line_intro' => 'Your booking at **:tenant** has been confirmed.',
        'line_signoff' => 'See you soon!',
    ],

    'booking_cancelled' => [
        'subject' => 'Booking cancelled — :tenant',
        'line_by_client' => 'Your booking at **:tenant** has been cancelled per your request.',
        'line_by_stable' => 'The stable **:tenant** cancelled your booking. Please contact them for details.',
        'pass_restored' => 'Your pass has been restored — you can use it for your next booking.',
        'pass_not_restored' => 'Your pass (if used) was not restored — cancellation came after the policy window.',
    ],

    'booking_reminder' => [
        'subject' => 'Reminder: tomorrow at :time — :tenant',
        'line_intro' => 'Reminder about your booking tomorrow.',
        'cancel_policy' => 'If you need to cancel, do it as soon as possible — cancelling at least :hours hours before the lesson is free of charge.',
        'line_signoff' => 'See you tomorrow!',
    ],

    'booking_requested' => [
        'subject' => 'We received your request — :tenant',
        'line_intro' => 'Thanks for your booking request at **:tenant**.',
        'line_processing' => 'The stable will confirm your booking by email (usually within a few hours) and assign a horse.',
        'line_pass_warning' => 'If you do not cancel in time, your pass (if used) will be consumed.',
    ],

    'booking_rescheduled' => [
        'subject' => 'Booking rescheduled — :tenant',
        'line_intro' => 'Your booking at **:tenant** has been rescheduled.',
        'line_undo' => 'If this reschedule was a mistake, you can cancel and book a new slot.',
        'portal_link' => 'Manage your bookings in the client portal: [:url](:url)',
    ],

    'client_portal_magic_link' => [
        'subject' => 'Sign in to client portal — :tenant',
        'line_intro' => 'Click below to sign in to the client portal at **:tenant**.',
        'action' => 'Sign in',
        'line_ttl' => 'This link is valid for :minutes minutes and can be used only once.',
        'line_security' => "If you didn't request this — please ignore this message.",
    ],

    'horse_message' => [
        'subject_default' => 'New message — :horse — :tenant',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'You received a new message about horse **:horse** (:tenant).',
        'attachments_one' => '📎 1 attachment',
        'attachments_many' => '📎 :count attachments',
        'action' => 'Open message',
    ],

    'owner_message_to_stable' => [
        'subject_default' => 'New message from horse owner — :horse',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Owner **:owner** sent a message about horse **:horse**.',
        'attachment_count' => '📎 Attachments: :count',
        'action' => 'Open horse in panel',
    ],

    'invoice_issued' => [
        'subject' => ':kind :number — :tenant',
        'line_intro' => 'We issued :kind **:number** from **:tenant**.',
        'action_pay' => 'View invoice and pay',
        'action_view' => 'View invoice',
        'line_offline_payment' => 'Please pay via bank transfer to the stable account — details available in the client portal.',
        'line_thanks' => 'Thank you!',
    ],

    'new_booking_request' => [
        'subject' => 'New online request — :tenant',
        'line_intro' => 'A client requested a lesson at **:tenant**:',
        'client_format' => ':name (:email)',
        'client_format_with_phone' => ':name (:email, phone :phone)',
        'line_action_required' => 'To accept, open the booking, assign a horse, and change status to "Confirmed".',
        'action' => 'Open booking',
        'line_horse_assignment' => 'A horse can only be assigned at confirmation time — the system requires it before changing status.',
        'salutation' => '— Hovera',
    ],

    'user_invitation' => [
        'subject_with_tenant' => 'Invitation to :tenant — Hovera',
        'subject_default' => 'Invitation to Hovera',
        'line_with_tenant' => "You've been added to **:tenant** in Hovera:role.",
        'line_with_tenant_role' => ' with role *:role*',
        'line_default' => 'You received an invitation to Hovera.',
        'line_setup' => 'To activate your account and set a password, click below.',
        'action' => 'Set password and sign in',
        'line_expires' => 'This link expires on :date (UTC).',
        'line_security' => "If this wasn't you, you can ignore this message — without clicking, the account won't be activated.",
        'salutation' => '— Hovera',
    ],

    // Faza 6 PR 6.1 — Owner notifications hub
    'owner_new_message' => [
        'subject_default' => 'New message — :horse — :stable',
        'subject_with_subject' => ':subject (:horse)',
        'line_intro' => 'Stable **:stable** sent a message about horse **:horse**.',
        'attachment_count' => '📎 Attachments: :count',
        'action' => 'Open message',
    ],

    'owner_new_invoice' => [
        'subject_default' => 'New invoice — :stable',
        'subject_with_number' => 'Invoice :number — :stable',
        'line_intro' => 'Stable **:stable** issued a new invoice.',
        'field' => [
            'number' => 'Number',
            'period' => 'Billing period',
            'horse' => 'Horse',
            'total' => 'Total (gross)',
            'due_at' => 'Due date',
        ],
        'action' => 'View invoice',
    ],

    'owner_vet_visit' => [
        'subject' => ':horse — :type',
        'line_intro' => 'Stable **:stable** recorded :type for horse **:horse**.',
        'field' => [
            'cost' => 'Cost',
            'next_due' => 'Next due',
        ],
        'action' => 'Open timeline',
    ],
];
