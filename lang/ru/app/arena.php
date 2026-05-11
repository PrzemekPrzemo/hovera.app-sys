<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Крытый манеж',
        'outdoor' => 'Открытый плац',
        'paddock' => 'Левада',
        'lunge' => 'Бочка для корды',
        'field' => 'Поле',
    ],

    'form' => [
        'label' => [
            'name' => 'Название',
            'type' => 'Тип',
            'color' => 'Цвет в календаре',
            'is_active' => 'Активен',
            'sort_order' => 'Порядок',
            'notes' => 'Заметки',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Название',
            'type' => 'Тип',
            'color' => 'Цвет',
            'is_active' => 'Активен',
        ],
    ],
];
