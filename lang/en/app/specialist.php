<?php

declare(strict_types=1);

return [
    'types' => [
        'vet' => 'Vet',
        'farrier' => 'Farrier',
    ],
    'types_short' => [
        'vet' => 'Vet',
        'farrier' => 'Farrier',
    ],

    'form' => [
        'section' => [
            'data' => 'Specialist data',
            'access' => 'Hovera account (optional)',
            'access_description' => 'Link to a stable employee account — only if the specialist logs into the panel and should see "My tasks". Most farriers/vets are external contractors — leave empty.',
        ],
        'label' => [
            'type' => 'Specialty',
            'name' => 'Full name',
            'phone' => 'Phone',
            'color' => 'Calendar color',
            'central_user' => 'Link to employee',
            'central_user_placeholder' => '— no account —',
            'is_active' => 'Active',
            'sort_order' => 'Order',
            'notes' => 'Notes',
        ],
        'helper' => [
            'central_user' => 'Lists only active stable members. Picking here enables a "My tasks" view for the signed-in specialist later.',
        ],
    ],

    'table' => [
        'column' => [
            'type' => 'Specialty',
            'name' => 'Full name',
            'phone' => 'Phone',
            'central_user' => 'Account',
            'is_active' => 'Active',
        ],
        'filter' => [
            'type' => 'Specialty',
            'has_account' => 'With Hovera account',
        ],
        'has_account_yes' => 'Yes',
        'has_account_no' => '—',
    ],
];
