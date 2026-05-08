<?php

declare(strict_types=1);

return [
    'roles' => [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'instructor' => 'Instructor',
        'employee' => 'Employee',
        'vet' => 'Vet',
        'viewer' => 'View only',
    ],

    'form' => [
        'label' => [
            'email' => 'User email',
            'name' => 'Full name (optional, only for new user)',
            'role' => 'Role in stable',
            'attach_email' => 'Email',
            'attach_name' => 'Full name (if new user)',
            'attach_role' => 'Role',
            'impersonate_reason' => 'Impersonation reason (GDPR audit)',
        ],
        'helper' => [
            'email' => "If the user doesn't exist, an account will be created with a generated password.",
            'impersonate_reason' => "Required. Every action during the impersonation session is tagged in the stable's audit_log.",
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'Email',
            'name' => 'Name',
            'role' => 'Role',
            'joined_at' => 'Joined',
            'revoked_at' => 'Revoked',
        ],
        'filter' => [
            'status_label' => 'Status',
            'status_placeholder' => 'Active and revoked',
            'status_true' => 'Only revoked',
            'status_false' => 'Only active',
        ],
    ],

    'action' => [
        'attach' => [
            'label' => 'Add member',
            'success_attached_title' => 'Member added',
            'success_attached_body' => 'Added :email to the stable.',
            'success_invited_title' => 'Invitation sent',
            'success_invited_body' => 'Invitation sent to :email. The link expires on :expires.',
        ],
        'revoke' => [
            'label' => 'Revoke access',
            'success' => 'Access revoked',
        ],
        'reactivate' => [
            'label' => 'Reactivate',
            'success' => 'Access reactivated',
        ],
        'impersonate' => [
            'label' => 'Sign in as',
            'submit' => 'Start impersonation',
        ],
    ],
];
