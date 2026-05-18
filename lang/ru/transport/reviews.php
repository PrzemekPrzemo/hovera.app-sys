<?php

declare(strict_types=1);

return [
    'navigation' => 'Отзывы клиентов',
    'model' => ['singular' => 'Отзыв', 'plural' => 'Отзывы'],
    'table' => ['column' => [
        'rating' => 'Оценка',
        'customer' => 'Клиент',
        'comment' => 'Комментарий',
        'status' => 'Статус',
        'responded' => 'Ответ',
        'submitted_at' => 'Дата',
    ]],
    'filter' => ['rating' => 'Оценка'],
    'status' => [
        'invited' => 'приглашён',
        'published' => 'опубликован',
        'hidden' => 'скрыт',
        'flagged' => 'отмечен',
        'expired' => 'истёк',
    ],
    'action' => ['respond' => 'Ответить публично', 'flag' => 'Сообщить о нарушении'],
    'form' => [
        'response_label' => 'Ваш ответ',
        'response_helper' => 'Ваш ответ виден публично под отзывом. Его можно изменить позже.',
        'flag_reason_label' => 'Причина',
        'flag_reason_helper' => 'Опишите, почему отзыв нарушает правила. Команда Hovera рассмотрит обращение.',
    ],
    'notify' => [
        'response_saved' => 'Ответ сохранён.',
        'flagged_title' => 'Отзыв отправлен на модерацию',
        'flagged_body' => 'Отзыв временно скрыт. Команда Hovera примет решение.',
    ],
    'stats' => [
        'average' => 'Средняя оценка',
        'count' => 'Всего отзывов',
        'count_desc' => 'опубликованных',
        'five_stars' => 'Оценок 5★',
        'no_reviews_yet' => 'Пока нет отзывов',
    ],
    'view' => ['section_review' => 'Отзыв', 'section_response' => 'Ваш ответ', 'section_moderation' => 'Модерация'],
];
