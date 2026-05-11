<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Тип',
            'performed_at' => 'Дата процедуры',
            'summary' => 'Краткое описание',
            'performed_by' => 'Выполнил',
            'performed_by_placeholder' => 'напр. помощник (если отличается от специалиста)',
            'specialist' => 'Специалист',
            'specialist_placeholder' => '— выберите из списка —',
            'next_due_at' => 'Следующая процедура',
            'cost' => 'Стоимость',
            'details' => 'Заметки',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Дата',
            'type' => 'Тип',
            'summary' => 'Описание',
            'performed_by' => 'Выполнил',
            'next_due_at' => 'Следующий',
        ],
    ],
];
