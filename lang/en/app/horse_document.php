<?php

declare(strict_types=1);

return [
    'uploaded_by' => [
        'stable' => 'Stable',
        'client' => 'Client',
    ],

    'form' => [
        'label' => [
            'name' => 'Document name',
            'name_placeholder' => 'e.g. Bucephalus passport',
            'kind' => 'Category',
            'description' => 'Description (optional)',
            'file' => 'File (max 25 MB)',
            'valid_from' => 'Valid from (optional)',
            'valid_until' => 'Valid until (optional)',
        ],
    ],

    'table' => [
        'column' => [
            'kind' => 'Category',
            'name' => 'Name',
            'original_name' => 'File',
            'size' => 'Size',
            'uploaded_by' => 'Uploaded by',
            'valid_until' => 'Valid until',
            'created_at' => 'Uploaded',
        ],
        'filter' => [
            'expiring_soon' => 'Expiring within 30 days',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Upload document',
            'no_file' => 'No file provided.',
            'failed' => 'Upload failed',
        ],
        'download' => [
            'label' => 'Download',
        ],
    ],
];
