<?php

declare(strict_types=1);

return [
    'title' => 'Мои бронирования — :tenant',
    'subtitle' => 'Клиентский портал · :tenant',
    'logout' => 'Выйти',

    'flash' => [
        'reschedule_success' => '✓ Бронирование перенесено. Мы отправили подтверждение по email.',
    ],

    'sections' => [
        'upcoming' => 'Предстоящие бронирования',
        'passes' => 'Ваши абонементы',
        'history' => 'История',
        'unpaid_invoices' => 'Счета к оплате',
        'messages' => 'Сообщения',
        'horses' => 'Ваши лошади',
    ],

    'empty' => [
        'upcoming' => 'Нет предстоящих бронирований.',
        'history' => 'Нет истории бронирований.',
    ],

    'duration_min' => ':minutes мин',
    'instructor_label' => 'Инструктор: :name',
    'horse_label' => 'Лошадь: :name',

    'status' => [
        'requested' => 'Ожидает',
        'confirmed' => 'Подтверждено',
        'completed' => 'Завершено',
        'cancelled' => 'Отменено',
        'no_show' => 'Неявка',
    ],

    'actions' => [
        'reschedule' => 'Перенести',
        'cancel' => 'Отменить',
        'view_all' => 'Все →',
    ],

    'pass' => [
        'remaining' => ':remaining / :total осталось',
        'valid_until' => 'действителен до :date',
        'recent_uses' => 'Недавно использовано',
        'lesson_label' => 'Занятие :date',
    ],

    'invoice' => [
        'issued_at' => 'Выставлен: :date',
        'due_at' => 'Срок: :date',
    ],

    'horse' => [
        'years_short' => 'л.',
        'overdue_pill' => ':count просроч.',
        'upcoming_pill' => ':count в 30 дн.',
        'ok_pill' => 'OK',
    ],

    // Russian 4-form plural (0/many / 1 / 2-4 / 5+).
    'unread_messages' => '{0} 📬 :count новых сообщений|{1} 📬 :count новое сообщение|[2,4] 📬 :count новых сообщения|[5,*] 📬 :count новых сообщений',
];
