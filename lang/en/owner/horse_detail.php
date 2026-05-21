<?php

declare(strict_types=1);

return [
    'title' => [
        'fallback' => 'Horse details',
    ],

    'breadcrumb' => 'Boarding details',

    'hero' => [
        'boarding_at' => 'Boarding at',
        'since' => 'Since',
    ],

    'section' => [
        'identification' => 'Identification',
        'current_box' => 'Current box',
        'boarding_services' => 'Active boarding services',
        'notes' => 'Stable notes',
    ],

    'field' => [
        'name' => 'Name',
        'breed' => 'Breed',
        'sex' => 'Sex',
        'color' => 'Color',
        'birth_date' => 'Date of birth',
        'age' => ':years years',
        'passport_number' => 'Passport No.',
        'microchip' => 'Microchip',
        'ueln' => 'UELN',
        'monthly_rate' => 'Monthly rate',
        'assigned_at' => 'Moved into box on :date',
        'estimated_monthly_cost' => 'Estimated monthly cost',
        'estimated_monthly_cost_hint' => 'Box rate + sum of active services. Actual invoice may include extras (one-off charges, treatments, etc.).',
    ],

    'frequency' => [
        'daily' => 'daily',
        'weekly' => 'weekly',
        'monthly' => 'monthly',
        'per_use' => 'per use',
        'once' => 'one-off',
    ],

    'table' => [
        'service_name' => 'Service',
        'frequency' => 'Frequency',
        'price' => 'Price',
    ],

    'empty' => [
        'no_box' => 'The stable has not assigned a box to this horse yet.',
        'no_services' => 'No active boarding services beyond the box rate.',
    ],

    'upcoming' => [
        'heading' => 'Coming soon to the owner panel',
        'timeline' => 'Timeline of all stable actions on the horse (vet visits, box changes, weighings, activities)',
        'invoices' => 'Invoices issued by the stable with per-horse breakdown + online payment',
        'messages' => 'Conversations with the stable — thread per horse with attachments (photos, documents)',
        'files' => 'Photo gallery and documents (passport, contracts, vaccination certificates)',
    ],

    'access' => [
        'denied' => 'No access to this horse. An active boarding assignment with a stable is required.',
        'sync_rift' => 'Horse data is not yet available in the stable — refresh in a moment or contact your caretaker.',
    ],
];
