<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Время и тип',
            'resources' => 'Ресурсы',
            'details' => 'Подробности',
            'participants' => 'Участники группового занятия',
            'participants_description' => 'Каждый участник = клиент + опционально лошадь. После занятия отмечаете посещаемость по каждому участнику.',
        ],
        'label' => [
            'type' => 'Тип',
            'starts_at' => 'Начало',
            'ends_at' => 'Конец',
            'horse' => 'Лошадь',
            'instructor' => 'Инструктор',
            'arena' => 'Манеж',
            'client' => 'Клиент',
            'title' => 'Название (для мероприятий / блокировок)',
            'status' => 'Статус',
            'price' => 'Цена',
            'notes' => 'Заметки',
            'participants' => 'Участники',
            'participant_client' => 'Клиент',
            'participant_horse' => 'Лошадь (опционально)',
            'participant_horse_placeholder' => '— на своей лошади / назначим позже —',
            'participant_attendance' => 'Посещаемость',
            'participant_notes' => 'Заметки (напр. «первое занятие»)',
        ],
    ],

    'attendance' => [
        'expected' => 'Ожидается',
        'present' => 'Присутствует',
        'absent' => 'Отсутствует',
        'late' => 'Опоздал',
    ],

    'actions' => [
        'add_participant' => '+ Добавить участника',
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Начало',
            'ends_at' => 'Конец',
            'type' => 'Тип',
            'horse' => 'Лошадь',
            'instructor' => 'Инструктор',
            'arena' => 'Манеж',
            'client' => 'Клиент',
            'status' => 'Статус',
        ],
        'participant_count' => '{0} нет участников|{1} 👥 :count участник|[2,4] 👥 :count участника|[5,*] 👥 :count участников',
        'filter' => [
            'horse' => 'Лошадь',
            'instructor' => 'Инструктор',
            'upcoming' => 'Только предстоящие',
        ],
    ],
];
