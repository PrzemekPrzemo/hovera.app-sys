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

    'verification_status' => [
        'pending' => 'Awaiting documents',
        'under_review' => 'Under review',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ],

    'transporter_document_type' => [
        'company_registration' => 'Company registration',
        'company_registration_description' => 'Business registry entry (CRO/KRS/CEIDG equivalent, PDF or image).',
        'animal_transport_cert' => 'Animal transport certificate',
        'animal_transport_cert_description' => 'EU Reg. 1/2005 certificate — required for horse transport.',
        'insurance_ocp' => 'Carrier liability insurance',
        'insurance_ocp_description' => 'Carrier liability policy (OCP equivalent).',
        'insurance_ocs' => 'Cargo insurance',
        'insurance_ocs_description' => 'Cargo insurance — covers damage to the transported animal.',
        'vehicle_registration' => 'Vehicle registration',
        'vehicle_registration_description' => 'Vehicle registration document scan — we check next-inspection date.',
        'other' => 'Other document',
        'other_description' => 'Custom — labour inspection certificate, EU community licence, etc.',
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
