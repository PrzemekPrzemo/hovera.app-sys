<?php

declare(strict_types=1);

return [
    'types' => [
        'individual' => 'Individual',
        'family' => 'Family',
        'organisation' => 'Company / organisation',
    ],
    'types_short' => [
        'individual' => 'Individual',
        'family' => 'Family',
        'organisation' => 'Company',
    ],

    'form' => [
        'section' => [
            'data' => 'Client data',
            'armir' => 'Horse owner identification (ARMiR)',
            'armir_description' => 'Required for owners of horses registered in the Polish Equine Central Database. EP (ARMiR producer ID) — if not assigned, enter PESEL.',
            'address' => 'Address',
            'rodo' => 'GDPR',
            'notes' => 'Notes',
        ],
        'label' => [
            'type' => 'Type',
            'name' => 'Full name / Company name',
            'phone' => 'Phone',
            'tax_id' => 'Tax ID / VAT ID',
            'armir_producer_id' => 'EP no. (ARMiR producer ID)',
            'armir_producer_id_placeholder' => 'e.g. 026123456789',
            'pesel' => 'PESEL',
            'street' => 'Street and number',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'country' => 'Country',
            'rodo_consent_at' => 'GDPR consent given',
            'rodo_consent_source' => 'Consent source',
            'notes' => 'Internal notes',
        ],
        'helper' => [
            'armir_producer_id' => 'Producer ID assigned by ARMiR when registering a horse.',
            'pesel' => 'Only if the owner does not have an EP assigned by ARMiR.',
        ],
        'gus' => [
            'lookup_label' => 'Fetch from GUS',
            'invalid_nip' => 'Invalid NIP (checksum failed).',
            'not_found' => 'Company not found in GUS.',
            'success' => 'Data fetched from GUS.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Type',
            'phone' => 'Phone',
            'horses_count' => 'Horses',
            'rodo' => 'GDPR',
            'created_at' => 'Added',
        ],
    ],

    'action' => [
        'issue_portal_link' => [
            'label' => 'Generate portal link',
            'modal_heading' => 'Generate sign-in link for :name?',
            'modal_description' => 'Creates a one-time magic link (TTL 30 min). You can copy and send it to the client manually, e.g. via SMS or Messenger. No email required.',
            'success_title' => 'Sign-in link created',
        ],
    ],
];
