<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Кобыла',
        'gelding' => 'Мерин',
        'stallion' => 'Жеребец',
        'breeding_stallion' => 'Племенной жеребец',
    ],

    'form' => [
        'section' => [
            'identification' => 'Идентификация',
            'characteristics' => 'Характеристики',
            'boarding' => 'Пансион — начисляемые услуги',
            'boarding_description' => 'Отметьте, какие позиции прайс-листа относятся к этой лошади. Клиент увидит их в портале с месячной ориентировочной суммой.',
            'notes' => 'Заметки',
        ],
        'label' => [
            'name' => 'Кличка',
            'owner' => 'Владелец',
            'owner_placeholder' => '— конюшня —',
            'box' => 'Денник',
            'box_placeholder' => '— без назначения —',
            'microchip' => 'Микрочип',
            'passport_number' => '№ паспорта',
            'ueln' => 'UELN',
            'sex' => 'Пол',
            'breed' => 'Порода',
            'color' => 'Масть',
            'birth_date' => 'Дата рождения',
            'boarding_services' => 'Услуги из прайс-листа',
        ],
        'helper' => [
            'box' => 'Изменение денника зарегистрирует историю в «Денники → История назначений».',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Прайс-лист настраивается в «Конюшня → Прайс-лист пансиона». Переопределение цены для каждой лошади (напр. скидка) задаётся там вручную после создания записи.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Кличка',
            'breed' => 'Порода',
            'sex' => 'Пол',
            'color' => 'Масть',
            'birth_date' => 'Род.',
            'owner' => 'Владелец',
            'owner_placeholder' => '— конюшня —',
            'created_at' => 'Добавлена',
        ],
    ],
];
