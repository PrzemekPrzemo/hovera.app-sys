<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'caption' => 'Caption (optional)',
            'sort_order' => 'Sort order',
            'file' => 'Photo (JPG/PNG/WEBP/HEIC, max 10 MB)',
        ],
    ],
    'table' => [
        'column' => [
            'thumb' => 'Thumb',
            'caption' => 'Caption',
            'sort_order' => 'Order',
            'size' => 'Size',
            'uploaded_by' => 'Uploaded by',
            'created_at' => 'Added',
        ],
    ],
    'uploaded_by' => [
        'stable' => 'Stable',
        'client' => 'Owner',
    ],
    'action' => [
        'upload' => 'Upload photo',
    ],
];
