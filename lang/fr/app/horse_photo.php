<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'caption' => 'Légende (optionnelle)',
            'sort_order' => 'Ordre',
            'file' => 'Photo (JPG/PNG/WEBP/HEIC, max 10 Mo)',
        ],
    ],
    'table' => [
        'column' => [
            'thumb' => 'Miniature',
            'caption' => 'Légende',
            'sort_order' => 'Ordre',
            'size' => 'Taille',
            'created_at' => 'Ajoutée le',
        ],
    ],
    'action' => [
        'upload' => 'Ajouter une photo',
    ],
];
