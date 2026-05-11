<?php

declare(strict_types=1);

return [
    'action' => [
        'create' => [
            'label' => 'Добавить бронирование',
            'modal_heading' => 'Новое бронирование',
            'success' => 'Бронирование добавлено',
            'conflict_title' => 'Конфликт',
        ],
        'edit' => [
            'label' => 'Редактировать бронирование',
            'modal_heading' => 'Редактирование бронирования',
            'success' => 'Бронирование обновлено',
        ],
        'delete' => [
            'label' => 'Удалить бронирование',
            'success' => 'Бронирование удалено',
        ],
    ],

    'form' => [
        'label' => [
            'type' => 'Тип',
            'starts_at' => 'Начало',
            'ends_at' => 'Конец',
            'horse' => 'Лошадь',
            'instructor' => 'Инструктор',
            'arena' => 'Манеж',
            'client' => 'Клиент',
            'title' => 'Название',
            'status' => 'Статус',
            'notes' => 'Заметки',
        ],
    ],
];
