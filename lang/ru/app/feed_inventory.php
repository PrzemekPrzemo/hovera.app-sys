<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'item' => 'Позиция корма',
        ],
        'label' => [
            'name' => 'Название',
            'unit' => 'Единица',
            'low_stock_threshold' => 'Порог уведомления',
            'sort_order' => 'Порядок',
            'is_active' => 'Активно',
            'notes' => 'Заметки',
            'kind' => 'Тип движения',
            'amount' => 'Количество (положительное)',
            'movement_date' => 'Дата',
            'movement_notes' => 'Заметки движения',
        ],
        'helper' => [
            'low_stock_threshold' => 'Остаток ниже этого значения покажет уведомление. Пусто = без уведомлений.',
            'amount' => 'Введите положительное значение — направление определяется типом движения.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Название',
            'current_stock' => 'Остаток',
            'low_stock_threshold' => 'Порог',
            'is_active' => 'Активно',
            'updated_at' => 'Последнее движение',
        ],
        'filter' => [
            'low_stock' => 'С порогом уведомления',
        ],
    ],
    'actions' => [
        'add_movement' => '+ Движение склада',
    ],
    'kind' => [
        'purchase' => 'Поступление / поставка',
        'consumption' => 'Выдача / расход',
        'adjustment' => 'Корректировка инвентаризации',
        'waste' => 'Списание / утилизация',
    ],
    'movements' => [
        'heading' => 'История движений',
        'col_date' => 'Дата',
        'col_kind' => 'Тип',
        'col_amount' => 'Изменение',
        'col_notes' => 'Заметки',
    ],
];
