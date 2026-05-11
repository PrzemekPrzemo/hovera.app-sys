<?php

declare(strict_types=1);

return [
    'days_of_week' => [
        '1' => 'Понедельник',
        '2' => 'Вторник',
        '3' => 'Среда',
        '4' => 'Четверг',
        '5' => 'Пятница',
        '6' => 'Суббота',
        '0' => 'Воскресенье',
    ],

    'form' => [
        'section' => [
            'basic' => 'Основное',
            'recurrence' => 'Повторяемость',
            'default_resources' => 'Ресурсы по умолчанию',
            'details' => 'Подробности',
        ],
        'label' => [
            'name' => 'Название серии',
            'name_placeholder' => 'Школа пн. 17:00',
            'type' => 'Тип',
            'starts_time' => 'Время начала',
            'duration_minutes' => 'Длительность (мин)',
            'pattern' => 'Шаблон',
            'interval' => 'Интервал',
            'days_of_week' => 'Дни недели',
            'recurrence_starts_on' => 'С',
            'recurrence_ends_on' => 'До (опционально)',
            'max_occurrences' => 'Лимит повторений',
            'max_occurrences_placeholder' => 'напр. 26',
            'horse' => 'Лошадь',
            'instructor' => 'Инструктор',
            'arena' => 'Манеж',
            'client' => 'Клиент',
            'title' => 'Название занятия',
            'price' => 'Цена',
            'is_active' => 'Серия активна',
            'notes' => 'Заметки',
        ],
        'helper' => [
            'interval' => '1 = каждый, 2 = через один…',
            'recurrence_ends_on' => 'Пусто = без конца; expander генерирует максимум 365 повторений за раз.',
            'max_occurrences' => 'Альтернатива дате окончания.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Название',
            'type' => 'Тип',
            'pattern' => 'Шаблон',
            'starts_time' => 'Время',
            'duration_minutes' => 'Мин',
            'recurrence_starts_on' => 'С',
            'recurrence_ends_on' => 'До',
            'recurrence_ends_on_empty' => '— без конца —',
            'occurrences_count' => 'Повторений',
            'is_active' => 'Активна',
        ],
        'filter' => [
            'status' => 'Статус',
        ],
    ],

    'action' => [
        'expand' => [
            'label' => 'Сгенерировать повторения',
            'success_title' => 'Серия развёрнута',
            'success_body' => 'Создано :count повторений.',
            'skipped' => ' Пропущено из-за конфликта: :list.',
        ],
        'cancel_series' => [
            'label' => 'Отменить серию',
            'modal_heading' => 'Отменить всю серию',
            'modal_description' => 'Прошедшие повторения сохраняются, будущие отменяются.',
            'success_title' => 'Серия отменена',
            'success_body' => 'Отменено :count будущих повторений.',
        ],
    ],
];
