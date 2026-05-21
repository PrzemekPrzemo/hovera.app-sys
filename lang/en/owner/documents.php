<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'You are not the owner of this horse.',
        'upload_requires_active_boarding' => 'Document upload requires an active boarding assignment. After boarding ends the list remains read-only.',
        'cannot_delete_stable' => 'You cannot delete a document uploaded by the stable.',
        'cannot_delete_other' => 'You can only delete your own documents.',
        'path_mismatch' => 'The file path does not belong to this stable.',
    ],

    'error' => [
        'too_large' => 'File ":name" exceeds the :max_mb MB limit.',
        'unsupported_mime' => 'Unsupported file type ":mime" (":name"). Allowed: PDF, Word, Excel, JPG/PNG/WebP.',
        'invalid_kind' => 'Invalid document kind: :kind.',
    ],

    'page' => [
        'title' => 'Horse documents',
        'breadcrumb' => 'Documents',
        'stable' => 'Stable',
        'empty_heading' => 'No documents',
        'empty_description' => 'Upload the first horse document (passport, contract, insurance, vaccination card).',
        'expired_badge' => 'Expired',
        'expiring_soon_badge' => 'Expires soon',
    ],

    'form' => [
        'section' => 'Upload document',
        'file' => 'File (PDF/Word/Excel/JPG/PNG, max 25 MB)',
        'name' => 'Document name',
        'kind' => 'Kind',
        'description' => 'Description (optional)',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'upload_button' => 'Upload',
        'uploaded' => 'Document uploaded.',
        'upload_failed' => 'Document upload failed.',
        'no_file' => 'Select a file to upload.',
        'delete' => 'Delete',
        'delete_confirm' => 'Are you sure you want to delete this document?',
        'deleted' => 'Document deleted.',
        'download' => 'Download',
    ],

    'uploader' => [
        'you' => 'You',
        'stable' => 'Stable',
    ],

    'table' => [
        'name' => 'Name',
        'kind' => 'Kind',
        'valid_until' => 'Valid until',
        'uploaded_by' => 'Uploaded by',
        'added' => 'Added',
        'actions' => 'Actions',
    ],
];
