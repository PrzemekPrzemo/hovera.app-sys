<?php

declare(strict_types=1);

return [
    'today' => [
        'bookings' => 'Бронирования сегодня',
        'bookings_desc' => 'Активные записи в календаре',

        'vacant_boxes' => 'Свободные денники',
        'vacant_boxes_desc' => 'Активные, со свободными местами',

        'overdue_care' => 'Просроченные процедуры',
        'overdue_care_desc' => 'Прививки / ковка / стоматолог просрочены',

        'unpaid_invoices' => 'Неоплаченные счета',
        // Russian 4-form plural — keyed by count.
        'unpaid_invoices_desc' => '{0} нет неоплаченных|{1} :count счёт выставлен|[2,4] :count счёта выставлено|[5,*] :count счетов выставлено',

        'bookings_table_heading' => 'Сегодняшние бронирования',
        'col_time' => 'Время',
        'col_horse' => 'Лошадь',
        'col_instructor' => 'Инструктор',
        'col_arena' => 'Манеж',
        'col_status' => 'Статус',
        'empty_heading' => 'Нет бронирований на сегодня',
        'empty_desc' => 'Спокойный день — или время для акции!',
    ],

    'livejumping' => [
        'heading' => 'Ближайшие старты (LiveJumping)',
        'description' => 'Лошади и всадники из вашей конюшни с указанными профилями LJ.',
        'empty' => 'Нет ближайших стартов. Добавьте URL профиля LiveJumping в карточке лошади или клиента.',
        'more_count' => '+ :count ещё',
    ],
];
