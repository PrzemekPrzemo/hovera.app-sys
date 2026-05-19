<?php

declare(strict_types=1);

return [
    'navigation' => 'Отзывы перевозчиков',
    'model' => ['singular' => 'Отзыв', 'plural' => 'Отзывы маркетплейса'],
    'table' => ['column' => [
        'transporter' => 'Перевозчик',
        'rating' => 'Оценка',
        'customer' => 'Клиент',
        'comment' => 'Комментарий',
        'status' => 'Статус',
        'flagged_at' => 'Отмечен',
    ]],
    'filter' => [
        'rating' => 'Оценка',
        'transporter' => 'Перевозчик',
        'flagged' => 'Отмечено перевозчиком',
    ],
    'action' => ['publish' => 'Опубликовать', 'hide' => 'Скрыть', 'reject' => 'Отклонить (удалить)'],
    'bulk' => [
        'publish' => 'Опубликовать выбранные',
        'publish_done' => 'Опубликовано :count отзывов',
        'hide' => 'Скрыть выбранные',
        'hide_done' => 'Скрыто :count отзывов',
    ],
    'form' => ['moderation_notes' => 'Заметки модератора'],
    'notify' => [
        'moderated' => 'Отзыв обновлён (статус: :status).',
        'rejected' => 'Отзыв отклонён и удалён.',
    ],
    'view' => ['section_review' => 'Отзыв', 'section_moderation' => 'Модерация'],
];
