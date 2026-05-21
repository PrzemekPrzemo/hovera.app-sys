<?php

declare(strict_types=1);

return [
    'access' => [
        'not_owner' => 'You are not the owner of this horse.',
        'upload_requires_active_boarding' => 'Photo upload requires an active boarding assignment. After boarding ends, the gallery remains read-only.',
        'cannot_delete_stable' => 'You cannot delete a photo uploaded by the stable.',
        'cannot_delete_other' => 'You can only delete your own photos.',
        'path_mismatch' => 'The file path does not belong to this stable.',
    ],

    'error' => [
        'too_large' => 'File ":name" exceeds the :max_mb MB limit.',
        'unsupported_mime' => 'Unsupported file type ":mime" (":name"). Allowed: JPG, PNG, WebP.',
    ],

    'page' => [
        'title' => 'Horse photo gallery',
        'breadcrumb' => 'Gallery',
        'stable' => 'Stable',
        'empty_heading' => 'No photos',
        'empty_description' => 'Upload the first photo of your horse — it will appear here alongside photos from the stable.',
    ],

    'form' => [
        'section' => 'Upload photo',
        'file' => 'File (JPG/PNG/WebP, max 10 MB)',
        'caption' => 'Caption (optional)',
        'upload_button' => 'Upload',
        'uploaded' => 'Photo uploaded.',
        'upload_failed' => 'Photo upload failed.',
        'no_file' => 'Select a file to upload.',
        'delete' => 'Delete',
        'delete_confirm' => 'Are you sure you want to delete this photo?',
        'deleted' => 'Photo deleted.',
    ],

    'uploader' => [
        'you' => 'You',
        'stable' => 'Stable',
    ],
];
