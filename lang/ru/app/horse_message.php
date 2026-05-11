<?php

declare(strict_types=1);

return [
    'directions' => [
        'from_stable' => 'Конюшня → Клиент',
        'from_client' => 'Клиент → Конюшня',
    ],

    'form' => [
        'label' => [
            'subject' => 'Тема (опционально)',
            'body' => 'Текст',
            'attachments' => 'Вложения (макс. 5, до 10 МБ каждое)',
        ],
    ],

    'table' => [
        'column' => [
            'sent_at' => 'Отправлено',
            'direction' => 'Направление',
            'subject' => 'Тема',
            'body' => 'Фрагмент',
            'attachments_short' => 'Влож.',
            'read_short' => 'Прочт.',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Написать владельцу',
            'failed' => 'Не удалось отправить',
            'sent' => 'Сообщение отправлено',
        ],
        'mark_read' => [
            'label' => 'Отметить как прочитанное',
            'success' => 'Отмечено как прочитанное',
        ],
    ],
];
