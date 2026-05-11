<?php

declare(strict_types=1);

return [
    'title' => 'Meine Buchungen — :tenant',
    'subtitle' => 'Kundenportal · :tenant',
    'logout' => 'Abmelden',

    'flash' => [
        'reschedule_success' => '✓ Buchung verschoben. Bestätigung wurde per E-Mail gesendet.',
    ],

    'sections' => [
        'upcoming' => 'Anstehende Buchungen',
        'passes' => 'Ihre Reitkarten',
        'history' => 'Verlauf',
        'unpaid_invoices' => 'Zu zahlende Rechnungen',
        'messages' => 'Nachrichten',
        'horses' => 'Ihre Pferde',
    ],

    'empty' => [
        'upcoming' => 'Keine anstehenden Buchungen.',
        'history' => 'Kein Buchungsverlauf.',
    ],

    'duration_min' => ':minutes Min.',
    'instructor_label' => 'Reitlehrer: :name',
    'horse_label' => 'Pferd: :name',

    'status' => [
        'requested' => 'Ausstehend',
        'confirmed' => 'Bestätigt',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
        'no_show' => 'No-Show',
    ],

    'actions' => [
        'reschedule' => 'Verschieben',
        'cancel' => 'Stornieren',
        'view_all' => 'Alle →',
    ],

    'pass' => [
        'remaining' => ':remaining / :total verbleibend',
        'valid_until' => 'gültig bis :date',
        'recent_uses' => 'Zuletzt genutzt',
        'lesson_label' => 'Reitstunde :date',
    ],

    'invoice' => [
        'issued_at' => 'Ausgestellt: :date',
        'due_at' => 'Fällig: :date',
    ],

    'horse' => [
        'years_short' => 'J.',
        'overdue_pill' => ':count überfäll.',
        'upcoming_pill' => ':count in 30 Tagen',
        'ok_pill' => 'OK',
    ],

    'unread_messages' => '{0} 📬 :count neue Nachrichten|{1} 📬 :count neue Nachricht|[2,*] 📬 :count neue Nachrichten',
];
