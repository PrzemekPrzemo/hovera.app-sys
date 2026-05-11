<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'caption' => 'Bildunterschrift (optional)',
            'sort_order' => 'Reihenfolge',
            'file' => 'Foto (JPG/PNG/WEBP/HEIC, max. 10 MB)',
        ],
    ],
    'table' => [
        'column' => [
            'thumb' => 'Vorschau',
            'caption' => 'Bildunterschrift',
            'sort_order' => 'Reihenfolge',
            'size' => 'Größe',
            'created_at' => 'Hinzugefügt',
        ],
    ],
    'action' => [
        'upload' => 'Foto hinzufügen',
    ],
];
