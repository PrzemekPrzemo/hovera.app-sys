<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'role' => 'Role',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'Email',
            'name' => 'Full name',
            'role' => 'Role',
            'joined_at' => 'Joined',
            'revoked_at' => 'Revoked',
        ],
    ],

    'action' => [
        'add' => [
            'label' => 'Add team member',
        ],
        'send_password_reset' => [
            'label' => 'Send password reset link',
            'modal_description' => "We'll email a password reset link to :email. The link expires in 60 minutes.",
            'success_title' => 'Password reset link sent',
            'success_body' => 'Email sent to :email. The employee should check their inbox (and spam folder).',
            'failure_title' => 'Failed to send link',
            'failure_no_email' => 'Team member has no email address on file.',
        ],
    ],
];
