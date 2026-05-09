<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'caption' => 'Podpis (opcjonalnie)',
            'sort_order' => 'Kolejność',
            'file' => 'Zdjęcie (JPG/PNG/WEBP/HEIC, max 10 MB)',
        ],
    ],
    'table' => [
        'column' => [
            'thumb' => 'Miniaturka',
            'caption' => 'Podpis',
            'sort_order' => 'Kolejność',
            'size' => 'Rozmiar',
            'created_at' => 'Dodano',
        ],
    ],
    'action' => [
        'upload' => 'Dodaj zdjęcie',
    ],
];
