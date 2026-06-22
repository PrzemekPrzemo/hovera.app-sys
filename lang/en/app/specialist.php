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

    'specialty' => [
        'vet' => 'Vet',
        'farrier' => 'Farrier',
        'groomer' => 'Groomer',
        'dietetyk' => 'Nutritionist',
        'other' => 'Other',
    ],

    'action' => [
        'create' => [
            'label' => 'Add local contact',
        ],
        'invite' => [
            'label' => 'Invite a vet',
            'email' => 'Vet email',
            'display_name' => 'Full name',
            'display_name_placeholder' => 'dr Anna Kowalska',
            'specialty' => 'Specialty',
            'modal_heading' => 'Invite specialist to Hovera',
            'modal_description' => 'The system will send a 7-day activation link to the provided email. After setting the password the specialist gets access to their panel. Account requires verification by the Hovera team before the "verified" badge appears.',
            'submit' => 'Send invitation',
            'no_tenant' => 'No tenant context — refresh the page and try again.',
            'notify' => [
                'created_title' => 'Invitation sent',
                'created_body' => 'The specialist (:email) will receive an email with the activation link (7 days).',
                'reissued_title' => 'New activation link sent',
                'reissued_body' => 'The specialist (:email) had an account without password — we sent a fresh link.',
                'already_setup_title' => 'Specialist already active',
                'already_setup_body' => ':email is already registered — we are not re-sending the invitation. To add them to your stable use the dedicated action (coming soon).',
            ],
        ],
    ],
];
