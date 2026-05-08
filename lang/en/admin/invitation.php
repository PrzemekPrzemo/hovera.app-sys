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
    ],
];
