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
            'created_at' => 'Added',
        ],
    ],
    'action' => [
        'upload' => 'Upload photo',
    ],
];
