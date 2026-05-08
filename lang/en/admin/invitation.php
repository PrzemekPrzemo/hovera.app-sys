<?php

declare(strict_types=1);

return [
    'table' => [
        'column' => [
            'tenant' => 'Stable',
            'role' => 'Role',
            'status' => 'Status',
            'invited_by' => 'Invited by',
            'expires_at' => 'Expires',
            'accepted_at' => 'Accepted',
            'created_at' => 'Sent',
        ],
        'status' => [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'expired' => 'Expired',
        ],
        'filter' => [
            'only_pending' => 'Only pending',
            'expired' => 'Only expired',
            'accepted' => 'Only accepted',
            'tenant' => 'Stable',
        ],
    ],
    'action' => [
        'resend' => [
            'label' => 'Resend',
            'success' => 'Invitation resent',
        ],
        'revoke' => [
            'label' => 'Revoke',
            'success' => 'Invitation revoked',
        ],
        'show_url' => [
            'label' => 'Show sign-in link',
            'modal_heading' => 'Sign-in link for :email',
            'modal_description' => 'Each invocation generates a NEW token (the previous one is invalidated). The raw token is not stored in DB — it appears here only once.',
            'success_title' => 'Link generated — copy below:',
        ],
        'resend_email' => [
            'label' => 'Send by email',
            'success_title' => 'Invitation sent to :email',
            'success_body' => "Link (copy if email is not delivered):\n:url",
        ],
    ],
];
