<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'caption' => 'Подпись (опционально)',
            'sort_order' => 'Порядок',
            'file' => 'Фото (JPG/PNG/WEBP/HEIC, макс. 10 МБ)',
        ],
    ],
    'table' => [
        'column' => [
            'thumb' => 'Миниатюра',
            'caption' => 'Подпись',
            'sort_order' => 'Порядок',
            'size' => 'Размер',
            'created_at' => 'Добавлено',
        ],
    ],
    'action' => [
        'upload' => 'Добавить фото',
    ],
];
