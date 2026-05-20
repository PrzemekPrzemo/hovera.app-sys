<?php

declare(strict_types=1);

return [
    'navigation' => 'Customers',
    'model_label' => 'Customer',
    'plural_label' => 'Customers',

    'section' => [
        'identification' => 'Basic info',
        'registry' => 'Company registries (NIP / KRS)',
        'registry_description' => 'Enter NIP / KRS, click 🔍 to fetch data from public MF (Biała Lista) / KRS registries. The data feeds the invoice.',
        'notes' => 'Internal notes',
    ],

    'form' => [
        'label' => [
            'name' => 'Name / full name',
            'company' => 'Legal company name',
            'email' => 'Email',
            'phone' => 'Phone',
            'tax_id' => 'Tax ID (NIP)',
            'krs_number' => 'KRS number',
            'address' => 'Billing address',
            'notes' => 'Notes',
            'last_verified' => 'Last verification',
        ],
        'value' => [
            'not_verified' => 'Not verified',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'company' => 'Company',
            'tax_id' => 'Tax ID',
            'email' => 'Email',
            'phone' => 'Phone',
            'verified' => 'Verified',
            'created_at' => 'Added',
        ],
    ],

    'action' => [
        'lookup_nip_tooltip' => 'Fetch from MF Biała Lista',
        'lookup_krs_tooltip' => 'Fetch from KRS registry',
    ],

    'notify' => [
        'lookup_empty_identifier' => 'Enter a NIP / KRS number before verifying',
        'lookup_success' => 'Data fetched from :source',
        'lookup_failed' => 'Could not fetch the data',
    ],
];
