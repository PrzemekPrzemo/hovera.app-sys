<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'measured_at' => 'Дата измерения',
            'weight_kg' => 'Вес',
            'girth_cm' => 'Обхват груди (girth)',
            'notes' => 'Заметки',
        ],
        'helper' => [
            'girth_cm' => 'Опционально — полезно, когда нет весов (формула обхват² × длина).',
        ],
    ],
    'table' => [
        'column' => [
            'measured_at' => 'Дата',
            'weight_kg' => 'Вес',
            'girth_cm' => 'Обхват',
            'trend' => 'Изменение',
            'notes' => 'Заметки',
        ],
    ],
];
