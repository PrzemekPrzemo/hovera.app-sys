<?php

declare(strict_types=1);

return [
    'uploaded_by' => [
        'stable' => 'Конюшня',
        'client' => 'Клиент',
    ],

    'form' => [
        'label' => [
            'name' => 'Название документа',
            'name_placeholder' => 'напр. Паспорт Буцефала',
            'kind' => 'Категория',
            'description' => 'Описание (опционально)',
            'file' => 'Файл (макс. 25 МБ)',
            'valid_from' => 'Действителен с (опционально)',
            'valid_until' => 'Действителен до (опционально)',
        ],
    ],

    'table' => [
        'column' => [
            'kind' => 'Категория',
            'name' => 'Название',
            'original_name' => 'Файл',
            'size' => 'Размер',
            'uploaded_by' => 'Загрузил',
            'valid_until' => 'Действителен до',
            'created_at' => 'Загружен',
        ],
        'filter' => [
            'expiring_soon' => 'Истекает в 30 дней',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Загрузить документ',
            'no_file' => 'Нет файла.',
            'failed' => 'Не удалось загрузить',
        ],
        'download' => [
            'label' => 'Скачать',
        ],
    ],
];
