<?php

declare(strict_types=1);

return [
    'section' => [
        'personal' => 'Personal data',
        'contact' => 'Contact',
        'license' => 'Driving license',
        'qualifications' => 'Additional qualifications',
        'qualifications_description' => 'Animal transport certificate (EU directive). ADR — for hazardous materials.',
        'other' => 'Other',
    ],

    'form' => [
        'label' => [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'date_of_birth' => 'Date of birth',
            'email' => 'Email',
            'phone' => 'Phone',
            'license_number' => 'License number',
            'license_categories' => 'Categories',
            'license_expires_at' => 'Valid until',
            'has_animal_transport_cert' => 'Animal transport certificate',
            'animal_transport_cert_expires_at' => 'Valid until',
            'has_adr' => 'ADR',
            'adr_expires_at' => 'Valid until',
            'hire_date' => 'Hire date',
            'is_active' => 'Active',
            'sort_order' => 'Sort order',
            'notes' => 'Notes',
        ],
        'helper' => [
            'email' => 'Address used for dispatch notifications.',
        ],
    ],

    'table' => [
        'column' => [
            'full_name' => 'Driver',
            'phone' => 'Phone',
            'email' => 'Email',
            'license_expires_at' => 'License until',
            'has_animal_transport_cert' => 'Animal transp.',
            'is_active' => 'Active',
        ],
    ],

    'filter' => [
        'license_expiring_soon' => 'License expires within 30 days',
    ],
];
