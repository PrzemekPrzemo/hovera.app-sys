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
            'public' => 'Public',
        ],
        'action' => [
            'preview' => 'Preview',
            'download' => 'Download',
            'upload_anonymized' => 'Upload anonymised version',
            'remove_anonymized' => 'Remove public version',
        ],
        'upload_anonymized' => [
            'modal_description' => 'The file (without PII) will appear on the public profile /t/{slug} as confirmation of the held document. Do not upload the original.',
            'file_label' => 'Anonymised document (PDF / JPG / PNG, max 5 MB)',
            'helper' => 'Remove from original: national ID number, home address, signatures, serial numbers. Keep: document type, expiry date, issuing authority.',
        ],
        'remove_anonymized' => [
            'modal_description' => 'The public file will be deleted. The document will disappear from the public profile /t/{slug}.',
        ],
        'public' => [
            'yes_tooltip' => 'Visible on the public profile.',
            'no_tooltip' => 'No anonymised version — not publicly visible.',
        ],
        'notify' => [
            'public_uploaded' => 'Public version uploaded — visible on /t/{slug}.',
            'public_removed' => 'Public version removed.',
        ],
        'missing_table_title' => 'Tenant DB needs migration',
        'missing_table_body' => 'The "transporter_documents" table does not exist in database :db. This tenant was provisioned before the verification documents feature was introduced. Fix: run `php artisan migrate --path=database/migrations/tenant --database=tenant` for this tenant. Showing an empty documents list as a fallback.',
    ],
];
