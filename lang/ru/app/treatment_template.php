<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'template' => 'Шаблон процедуры',
        ],
        'label' => [
            'name' => 'Название',
            'type' => 'Тип процедуры',
            'interval_days' => 'Частота (дней)',
            'sort_order' => 'Порядок',
            'default_summary' => 'Описание по умолчанию',
            'default_notes' => 'Заметки по умолчанию',
            'is_active' => 'Активен',
        ],
        'helper' => [
            'interval_days' => 'Количество дней до следующего визита. Пусто = разовая процедура без следующего срока.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Название',
            'type' => 'Тип',
            'interval' => 'Интервал',
            'is_active' => 'Активен',
        ],
        'days' => 'дней',
    ],
];
