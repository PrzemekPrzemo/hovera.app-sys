<?php

declare(strict_types=1);

return [
    'navigation' => 'Transport companies',

    'model' => [
        'singular' => 'transport company',
        'plural' => 'Transport companies',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'verification' => 'Verification',
            'verification_description' => 'The company uploads documents in their panel (/transport/transporter-documents). Review and approve or reject with a note.',
            'subscription' => 'Subscription',
        ],
        'label' => [
            'tax_id' => 'Tax ID / VAT',
            'verification_status' => 'Status',
            'verified_at' => 'Verified at',
            'verification_notes' => 'Notes / reason',
            'rejection_reason' => 'Rejection reason',
            'plan' => 'Plan',
        ],
        'helper' => [
            'verification_status' => 'Changed only by "Verify" / "Reject" actions.',
            'verification_notes' => 'Visible to the transport company.',
        ],
    ],

    'table' => [
        'column' => [
            'verification' => 'Verification',
            'plan' => 'Plan',
            'subscription' => 'Subscription',
            'last_activity_at' => 'Last activity',
            'verified_at' => 'Verified at',
            'created_at' => 'Created',
        ],
    ],

    'action' => [
        'verify' => 'Approve account',
        'reject' => 'Reject account',
        'feature' => 'Mark as Featured',
        'unfeature' => 'Unmark Featured',
        'login_as_owner' => [
            'label' => 'Log in as transporter',
            'reason_label' => 'Reason for impersonation (GDPR audit)',
            'reason_helper' => 'Required. Session is recorded in impersonation_sessions + audit_log_master.',
            'submit' => 'Start impersonation',
            'no_user_title' => 'No active user for this company',
            'no_user_body' => 'Add a team member or invite an owner first.',
        ],
    ],

    'notify' => [
        'verified' => 'Account approved',
        'verified_body' => 'Company :name activated. They can now send quotes and invoices.',
        'rejected' => 'Account rejected',
        'rejected_body' => 'Company :name rejected. They received an email with the reason.',
        'featured' => 'Marked as Featured',
        'unfeatured' => 'Featured status removed',
    ],

    'documents' => [
        'title' => 'Verification documents',
        'column' => [
            'type' => 'Document type',
            'status' => 'Status',
            'filename' => 'File',
            'uploaded_at' => 'Uploaded',
        ],
        'action' => [
            'preview' => 'Preview',
            'download' => 'Download',
        ],
        'missing_table_title' => 'Tenant DB needs migration',
        'missing_table_body' => 'The "transporter_documents" table does not exist in database :db. This tenant was provisioned before the verification documents feature was introduced. Fix: run `php artisan migrate --path=database/migrations/tenant --database=tenant` for this tenant. Showing an empty documents list as a fallback.',
    ],
];
