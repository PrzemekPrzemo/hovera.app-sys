<?php

declare(strict_types=1);

return [
    'form' => [
        'helper' => [
            'code' => 'Идентификатор (уникальный в рамках тарифа), напр. horses_plus_10.',
            'name' => 'Маркетинговая метка, напр. "+10 лошадей".',
            'resource_type' => 'Тип лимита/ресурса, который увеличивает дополнение.',
            'quantity' => 'На сколько увеличивает лимит (напр. 10 для "+10 лошадей").',
            'sort_order' => 'Меньше = выше в списке.',
        ],
        'label' => [
            'resource_type' => 'Тип ресурса',
            'quantity' => 'Количество',
            'price_monthly' => 'Месячная цена',
            'price_yearly' => 'Годовая цена',
            'is_active' => 'Активно',
        ],
        'resource_types' => [
            'horses' => 'Лошади',
            'users' => 'Пользователи',
            'clients' => 'Клиенты',
            'storage_gb' => 'Хранилище (ГБ)',
            'custom' => 'Прочее',
        ],
    ],
    'table' => [
        'column' => [
            'resource_type' => 'Ресурс',
            'quantity' => 'Кол-во',
            'price_monthly_short' => 'Мес.',
            'price_yearly' => 'Год',
            'is_active_short' => 'Акт.',
        ],
        'resource_types_short' => [
            'horses' => 'Лошади',
            'users' => 'Пользователи',
            'clients' => 'Клиенты',
            'storage_gb' => 'ГБ',
            'custom' => 'Прочее',
        ],
    ],
];
