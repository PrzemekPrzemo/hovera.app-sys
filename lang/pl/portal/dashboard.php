<?php

declare(strict_types=1);

return [
    'title' => 'Moje rezerwacje — :tenant',
    'subtitle' => 'Panel klienta · :tenant',
    'logout' => 'Wyloguj',

    'flash' => [
        'reschedule_success' => '✓ Rezerwacja przesunięta. Wysłaliśmy potwierdzenie mailem.',
    ],

    'sections' => [
        'upcoming' => 'Nadchodzące rezerwacje',
        'passes' => 'Twoje karnety',
        'history' => 'Historia',
        'unpaid_invoices' => 'Faktury do opłacenia',
        'messages' => 'Wiadomości',
        'horses' => 'Twoje konie',
    ],

    'empty' => [
        'upcoming' => 'Brak nadchodzących rezerwacji.',
        'history' => 'Brak historii rezerwacji.',
    ],

    'duration_min' => ':minutes min',
    'instructor_label' => 'Instruktor: :name',
    'horse_label' => 'Koń: :name',

    'status' => [
        'requested' => 'Oczekuje',
        'confirmed' => 'Potwierdzona',
        'completed' => 'Zakończona',
        'cancelled' => 'Odwołana',
        'no_show' => 'No-show',
    ],

    'actions' => [
        'reschedule' => 'Przesuń',
        'cancel' => 'Odwołaj',
        'view_all' => 'Wszystkie →',
    ],

    'pass' => [
        'remaining' => ':remaining / :total pozostało',
        'valid_until' => 'ważny do :date',
        'recent_uses' => 'Ostatnio użyte',
        'lesson_label' => 'Lekcja :date',
    ],

    'invoice' => [
        'issued_at' => 'Wystawiona: :date',
        'due_at' => 'Termin: :date',
    ],

    'horse' => [
        'years_short' => 'l.',
        'overdue_pill' => ':count przeterm.',
        'upcoming_pill' => ':count w 30 dni',
        'ok_pill' => 'OK',
    ],

    'unread_messages' => [
        'one' => '📬 :count nowa wiadomość',
        'other' => '📬 :count nowych wiadomości',
    ],
];
