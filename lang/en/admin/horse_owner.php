<?php

declare(strict_types=1);

return [
    'navigation' => 'Horse owners',
    'navigation_group' => 'Tenants',

    'model' => [
        'singular' => 'Horse owner',
        'plural' => 'Horse owners',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'owner_account' => 'Owner account',
            'owner_account_description' => 'User account linked to this tenant. Email is read from central `User` (via `memberships` with role=owner).',
            'metadata' => 'Metadata (read-only)',
        ],
        'label' => [
            'name' => 'Name',
            'slug' => 'Slug (auto-generated)',
            'status' => 'Status',
            'terms_accepted_at' => 'Terms accepted at',
            'owner_email' => 'Owner email',
            'owner_phone' => 'Phone',
            'country' => 'Country',
            'locale' => 'Locale',
            'timezone' => 'Timezone',
            'created_at' => 'Registered at',
        ],
        'helper' => [
            'slug' => 'Auto-generated from email at registration. Immutable.',
        ],
        'option' => [
            'status' => [
                'active' => 'Active',
                'suspended' => 'Suspended',
            ],
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'owner_email' => 'Owner email',
            'status' => 'Status',
            'slug' => 'Slug',
            'created_at' => 'Registered',
        ],
    ],

    'action' => [
        'force_password_reset' => [
            'label' => 'Send password reset',
            'modal_description' => 'Send password reset email to :email. Link valid for 60 minutes.',
            'no_owner' => 'No owner account — no recipient for the reset email',
            'success' => 'Email sent',
            'success_body' => 'Password reset link sent to :email.',
            'failed' => 'Send failed',
        ],
        'login_as_owner' => [
            'label' => 'Login as owner',
            'reason_label' => 'Reason (logged in master admin audit)',
            'reason_helper' => 'Short note explaining why you need to take over — e.g. "user reported a UI bug, investigating", "support ticket #123".',
            'no_user_title' => 'No user found',
            'no_user_body' => 'This tenant has no users in memberships yet. Check if registration completed successfully.',
            'submit' => 'Enter panel',
        ],
    ],
];
