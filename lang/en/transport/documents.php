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

    'section' => [
        'pwl_required' => 'PLW documents (required for verification)',
        'pwl_optional' => 'Optional documents',
        'legacy' => 'Legacy documents (do not count toward PLW)',
    ],

    'helper' => [
        'pwl_authorization_choice' => 'Choose Type 1 OR Type 2 — depending on your transport profile. Type 2 (> 8h) also covers Type 1.',
        'pwl_vehicle_per_vehicle' => 'Issued per vehicle. If you operate a fleet, upload a merged PDF covering all vehicles.',
        'wash_log_period' => 'Keep up to date — entries older than 12 months are treated as outdated.',
    ],

    'checklist' => [
        'heading' => 'PLW required documents checklist',
        'progress' => ':done of :total documents verified',
        'missing_intro' => 'Missing:',
        'all_complete' => 'All required documents verified.',
        'pwl_authorization_alternative' => 'PLW authorization (Type 1 OR Type 2)',
    ],

    'admin' => [
        'verify_doc' => 'Approve document',
        'reject_doc' => 'Reject document',
        'verify_doc_confirm' => 'Approve this document? Once approved, the transporter can no longer delete it.',
        'rejection_reason_required' => 'Rejection reason (visible to the transporter)',
        'notify_doc_verified' => 'Document approved',
        'notify_doc_rejected' => 'Document rejected',
        'cannot_verify_tenant' => 'First verify all required PLW documents (:done/:total). See the checklist below.',
    ],

    'expiry_notify' => [
        'subject' => 'Document :type expires in :days days',
        'greeting' => 'Hi,',
        'intro' => 'The document ":type" for company :name expires on :date (in :days days).',
        'cta' => 'Upload a new one in the panel — otherwise your account may be temporarily suspended on the expiry date.',
        'action' => 'Open documents',
    ],
];
