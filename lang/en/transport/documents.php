<?php

declare(strict_types=1);

return [
    'navigation' => 'Account verification',
    'title' => 'Verification documents',

    'status' => [
        'heading' => 'Account verification status',
        'pending_body' => 'To activate your account, please upload :count missing documents. Without verification you cannot send quotes or invoices.',
        'under_review_body' => 'All required documents uploaded — Hovera team is reviewing (typically 1–2 business days).',
        'verified_body' => 'Account active. You can send quotes, issue invoices, receive marketplace inquiries.',
        'rejected_body' => 'Account rejected. Check the notes on individual documents and re-upload corrected versions.',
        'missing_badge' => ':count missing',
    ],

    'label' => [
        'required' => 'required',
        'optional' => 'optional',
        'uploaded_at' => 'Uploaded',
        'expires_at' => 'Valid until',
        'issued_at' => 'Issue date',
        'expired' => 'EXPIRED',
        'expiring_soon' => 'expiring soon',
        'rejection_reason' => 'Rejection reason',
    ],

    'action' => [
        'upload' => 'Upload',
        'delete' => 'Delete',
    ],

    'confirm' => [
        'delete' => 'Delete this document? This action cannot be undone.',
    ],

    'notify' => [
        'uploaded' => 'Document uploaded',
        'deleted' => 'Document deleted',
        'error' => 'Error',
    ],

    'error' => [
        'no_file' => 'Select a file before clicking "Upload".',
        'bad_mime' => 'File format not allowed. Allowed: :allowed.',
        'too_large' => 'File too large. Maximum :limit.',
    ],

    'footer' => [
        'allowed_formats' => 'Accepted: PDF, JPG, PNG. Maximum 10 MB per file. All files stored in encrypted EU storage.',
    ],
];
