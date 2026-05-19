<?php

declare(strict_types=1);

return [
    'navigation' => 'Заявки',

    'section' => [
        'customer' => 'Клиент',
        'route' => 'Маршрут',
        'cargo' => 'Груз',
        'lifecycle' => 'Жизненный цикл',
    ],

    'label' => [
        'name' => 'ФИО',
        'email' => 'Email',
        'phone' => 'Телефон',
        'from' => 'Откуда',
        'to' => 'Куда',
        'pickup_voivodeship' => 'Воеводство (погрузка)',
        'dropoff_voivodeship' => 'Воеводство (выгрузка)',
        'preferred_date' => 'Дата',
        'preferred_time' => 'Время',
        'horse_count' => 'Лошадей',
        'flexible_date' => 'Дата гибкая',
        'notes' => 'Заметки клиента',
        'status' => 'Статус',
        'mode' => 'Режим',
        'expires_at' => 'Истекает',
    ],

    'table' => [
        'column' => [
            'customer' => 'Клиент',
            'route' => 'Маршрут',
            'preferred_date' => 'Дата',
            'horse_count' => 'Лошадей',
            'status' => 'Статус',
            'expires_at' => 'Истекает',
            'created_at' => 'Получено',
        ],
    ],

    'action' => [
        'respond' => 'Ответить предложением',
        'open_in_calculator' => 'Открыть в калькуляторе',
    ],

    'notify' => [
        'respond_started' => 'Форма предложения открыта',
        'respond_started_body' => 'Заполните детали и отправьте. Клиент увидит ваше предложение.',
    ],
];
