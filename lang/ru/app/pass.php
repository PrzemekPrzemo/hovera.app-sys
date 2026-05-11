<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'pass' => 'Абонемент',
        ],
        'label' => [
            'client' => 'Клиент',
            'name' => 'Название',
            'name_placeholder' => 'Абонемент на 8 занятий',
            'total_uses' => 'Количество занятий',
            'remaining_uses' => 'Осталось',
            'valid_from' => 'Действителен с',
            'valid_until' => 'Действителен до',
            'price' => 'Цена абонемента',
            'cancellation_policy_hours' => 'Политика отмены (ч)',
            'cancellation_policy_placeholder' => 'использовать значение из настроек конюшни',
            'status' => 'Статус',
            'notes' => 'Заметки',
        ],
        'helper' => [
            'remaining_uses' => 'Обновляется автоматически системой; ручное изменение — только в исключительных ситуациях.',
            'cancellation_policy_hours' => 'Отмена за X часов до занятия = бесплатно (абонемент возвращается).',
        ],
    ],

    'table' => [
        'column' => [
            'client' => 'Клиент',
            'name' => 'Абонемент',
            'remaining_uses' => 'Осталось',
            'status' => 'Статус',
            'valid_until' => 'Действителен до',
            'price' => 'Цена',
            'cancellation_policy' => 'Отмена',
            'cancellation_policy_default' => 'по настройкам конюшни',
            'created_at' => 'Выдан',
        ],
        'filter' => [
            'client' => 'Клиент',
        ],
    ],
];
