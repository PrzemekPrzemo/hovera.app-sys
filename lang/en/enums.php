<?php

declare(strict_types=1);

return [
    'tenant_type' => [
        'stable' => 'Stable',
        'transporter' => 'Transport company',
    ],

    'quote_status' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
        'withdrawn' => 'Withdrawn',
    ],

    'boarding_frequency' => [
        'daily' => 'Daily',
        'monthly' => 'Monthly',
        'per_use' => 'Per use',
        'once' => 'One-time',
    ],

    'calendar_entry_status' => [
        'requested' => 'Requested',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'no_show' => 'No-show',
    ],

    'calendar_entry_type' => [
        'lesson_individual' => 'Individual lesson',
        'lesson_group' => 'Group lesson',
        'training' => 'Training',
        'care' => 'Care',
        'event' => 'Event / competition',
        'block' => 'Block',
    ],

    'health_record_type' => [
        'vaccination' => 'Vaccination',
        'deworming' => 'Deworming',
        'vet_visit' => 'Vet visit',
        'farrier' => 'Farrier',
        'dentist' => 'Dentist',
        'check_up' => 'Check-up',
        'medication' => 'Medication',
        'other' => 'Other',
    ],

    'horse_document_kind' => [
        'passport' => 'Passport',
        'contract' => 'Contract',
        'insurance' => 'Insurance',
        'vaccine_book' => 'Vaccination book',
        'ownership_proof' => 'Proof of ownership',
        'competition_licence' => 'Competition licence',
        'vet_certificate' => 'Vet certificate',
        'other' => 'Other',
    ],

    'invoice_kind' => [
        'fv' => 'VAT invoice',
        'fv_proforma' => 'Proforma invoice',
        'fv_korekta' => 'Corrective invoice',
    ],

    'invoice_status' => [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'void' => 'Voided',
        'cancelled' => 'Cancelled',
    ],

    'pass_status' => [
        'active' => 'Active',
        'exhausted' => 'Used up',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ],

    'payment_provider' => [
        'none' => 'None',
        'stub' => 'Test (sandbox)',
        'p24' => 'Przelewy24',
        'payu' => 'PayU',
        'stripe' => 'Stripe',
        'mollie' => 'Mollie',
    ],

    'payment_status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'succeeded' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],

    'recurrence_pattern' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ],

    'stable_activity_type' => [
        'feeding' => 'Feeding',
        'grooming' => 'Grooming',
        'turnout' => 'Turnout',
        'exercise' => 'Working with horse',
        'box_cleaning' => 'Box cleaning',
        'transport_event' => 'Transport / event',
        'other' => 'Other',
    ],

    'feeding_meal' => [
        'breakfast' => 'Morning',
        'midday' => 'Midday',
        'evening' => 'Evening',
        'night' => 'Night',
    ],
];
