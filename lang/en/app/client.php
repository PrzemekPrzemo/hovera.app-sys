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
            'address' => 'Address',
            'rodo' => 'GDPR',
            'notes' => 'Notes',
        ],
        'label' => [
            'type' => 'Type',
            'name' => 'Full name / Company name',
            'phone' => 'Phone',
            'tax_id' => 'Tax ID / VAT ID',
            'street' => 'Street and number',
            'postal_code' => 'Postal code',
            'city' => 'City',
            'country' => 'Country',
            'rodo_consent_at' => 'GDPR consent given',
            'rodo_consent_source' => 'Consent source',
            'notes' => 'Internal notes',
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
