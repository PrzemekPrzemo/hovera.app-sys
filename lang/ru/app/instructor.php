<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Данные инструктора',
        ],
        'label' => [
            'name' => 'Имя и фамилия',
            'phone' => 'Телефон',
            'hourly_rate' => 'Ставка за час',
            'color' => 'Цвет в календаре',
            'is_active' => 'Активен',
            'notes' => 'Заметки',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Имя и фамилия',
            'phone' => 'Телефон',
            'hourly_rate' => 'Ставка',
            'color' => 'Цвет',
            'is_active' => 'Активен',
        ],
        'filter' => [
            'status' => 'Статус',
        ],
    ],

    'actions' => [
        'ics_url' => 'Календарь .ics',
    ],
    'ics_modal' => [
        'heading' => 'Календарь инструктора :name',
        'description' => 'Скопируйте URL и вставьте в Google Calendar / Outlook / Apple Calendar как «Добавить календарь по URL». Занятия появятся автоматически и будут синхронизироваться каждые несколько часов.',
        'url_label' => 'URL фида (подписка)',
        'howto' => 'Google Calendar → «Другие календари» → «+ → По URL» → вставьте URL. Outlook → «Добавить календарь → Подписаться из интернета». Apple → File → New Calendar Subscription.',
        'token_ensured' => 'URL готов',
        'close' => 'Закрыть',
    ],
];
