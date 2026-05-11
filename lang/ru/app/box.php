<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Внутренний денник',
        'paddock' => 'Левада',
        'outdoor' => 'Внешний денник',
        'quarantine' => 'Карантин',
    ],
    'types_short' => [
        'indoor' => 'Внутренний',
        'paddock' => 'Левада',
        'outdoor' => 'Внешний',
        'quarantine' => 'Карантин',
    ],

    'form' => [
        'section' => [
            'box' => 'Денник',
            'notes' => 'Заметки',
        ],
        'label' => [
            'building' => 'Здание',
            'building_placeholder' => '— без здания —',
            'name' => 'Название / номер',
            'label_short' => 'Короткий код (напр. "12")',
            'type' => 'Тип',
            'size_m2' => 'Размер (м²)',
            'capacity' => 'Вместимость',
            'monthly_rate' => 'Месячная цена пансиона',
            'is_active' => 'Активен',
            'sort_order' => 'Порядок',
            'notes' => 'Заметки',
        ],
        'helper' => [
            'capacity' => 'Сколько лошадей может быть в этом деннике (обычно 1; большие групповые денники могут вмещать больше).',
            'monthly_rate' => 'Тариф по умолчанию — можно переопределить для каждой лошади или клиента.',
        ],
    ],

    'table' => [
        'column' => [
            'building' => 'Здание',
            'building_none' => '— без здания —',
            'name' => 'Название',
            'type' => 'Тип',
            'size_m2' => 'м²',
            'status' => 'Статус',
            'horse_sex' => 'Пол лошади',
            'monthly_rate' => 'Пансион',
            'is_active' => 'Активен',
        ],
        'status' => [
            'free' => 'Свободен',
            'occupied' => 'Занят',
        ],
        'filter' => [
            'building' => 'Здание',
            'vacant' => 'Только свободные',
            'only_active' => 'Только активные',
        ],
    ],
];
