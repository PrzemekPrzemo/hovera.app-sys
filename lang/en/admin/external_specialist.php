<?php

declare(strict_types=1);

return [
    'navigation' => 'External specialists',

    'model' => [
        'singular' => 'specialist',
        'plural' => 'External specialists',
    ],

    'form' => [
        'section' => [
            'identity' => 'Identity data',
            'status' => 'Account status',
        ],
        'label' => [
            'email' => 'Email',
            'display_name' => 'Full name',
            'specialty' => 'Specialty',
            'phone' => 'Phone',
            'setup_status' => 'Account activation',
            'verified_at' => 'Verified at',
        ],
    ],

    'status' => [
        'setup_complete' => 'Password set, email confirmed',
        'setup_pending' => 'Awaiting password setup',
        'not_verified' => 'Not verified',
    ],

    'table' => [
        'email' => 'Email',
        'display_name' => 'Full name',
        'specialty' => 'Specialty',
        'setup' => 'Password',
        'verified' => 'Verified',
        'created_at' => 'Added',
    ],

    'filter' => [
        'not_verified' => 'Not verified',
        'setup_pending' => 'No password',
    ],

    'action' => [
        'verify' => [
            'label' => 'Verify',
            'modal_heading' => 'Verify specialist',
            'modal_description' => 'After verification the specialist will be marked as "verified" in communication thread views. Confirm their PWZ / license / references first.',
            'notify_title' => 'Specialist verified',
            'notify_body' => ':email is now marked as verified.',
        ],
        'unverify' => [
            'label' => 'Revoke verification',
            'modal_heading' => 'Revoke specialist verification',
            'modal_description' => 'Revoking removes the "verified" badge in communication views. Use when license has expired, data turned out false, or at specialist\'s request.',
            'reason' => 'Reason (audit log)',
            'notify_title' => 'Verification revoked',
            'notify_body' => ':email is no longer marked as verified.',
        ],
    ],
];
