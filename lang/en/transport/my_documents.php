<?php

declare(strict_types=1);

return [
    'navigation' => 'My documents',
    'title' => 'My professional documents',

    'no_driver_record' => 'Your account is not yet linked to a driver in the database. Contact your company operator.',
    'empty' => 'No documents on file. The operator adds them under "Drivers".',
    'hint' => 'Document edits are done by the company operator. Report any number changes or renewals to them.',
    'expires_at' => 'valid until :date',

    'status' => [
        'ok' => '✓ current',
        'soon' => '⚠ expires within 30 days',
        'expired' => '✕ expired',
    ],

    'doc' => [
        'license' => 'Driving licence',
        'animal_cert' => 'Animal transport competency certificate',
        'adr' => 'ADR qualification',
        'value_present' => '(on file)',
    ],
];
