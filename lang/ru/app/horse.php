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
            'sport' => 'Спорт (LiveJumping)',
            'sport_help' => 'Вставьте URL профиля лошади с LiveJumping.com — мы покажем palmares и ближайшие старты.',
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
            'livejumping_profile_url' => 'URL профиля LiveJumping',
            'livejumping_palmares' => 'Palmares',
        ],
        'helper' => [
            'box' => 'Изменение денника зарегистрирует историю в «Денники → История назначений».',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Прайс-лист настраивается в «Конюшня → Прайс-лист пансиона». Переопределение цены для каждой лошади (напр. скидка) задаётся там вручную после создания записи.',
            'livejumping_profile_url' => 'Скопируйте URL страницы профиля с livejumping.com — напр. https://livejumping.com/horse/12345/romeo',
            'livejumping_no_profile' => 'Вставьте URL профиля LJ выше, чтобы увидеть palmares.',
            'livejumping_fetch_failed' => 'Не удалось получить данные с LiveJumping (проверьте URL или попробуйте позже).',
        ],
        'stats' => [
            'starts' => 'Старты',
            'wins' => 'Победы',
            'placings' => 'Призовые места',
            'ranking_points' => 'Очки рейтинга',
            'recent_results' => 'Последние результаты',
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
