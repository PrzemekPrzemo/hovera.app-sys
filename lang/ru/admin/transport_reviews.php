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
    'filter' => ['rating' => 'Оценка', 'transporter' => 'Перевозчик'],
    'action' => ['publish' => 'Опубликовать', 'hide' => 'Скрыть', 'reject' => 'Отклонить (удалить)'],
    'form' => ['moderation_notes' => 'Заметки модератора'],
    'notify' => [
        'moderated' => 'Отзыв обновлён (статус: :status).',
        'rejected' => 'Отзыв отклонён и удалён.',
    ],
    'view' => ['section_review' => 'Отзыв', 'section_moderation' => 'Модерация'],
];
