<?php

declare(strict_types=1);

return [
    'days_of_week' => [
        '1' => 'Montag',
        '2' => 'Dienstag',
        '3' => 'Mittwoch',
        '4' => 'Donnerstag',
        '5' => 'Freitag',
        '6' => 'Samstag',
        '0' => 'Sonntag',
    ],

    'form' => [
        'section' => [
            'basic' => 'Grunddaten',
            'recurrence' => 'Wiederholung',
            'default_resources' => 'Standard-Ressourcen',
            'details' => 'Details',
        ],
        'label' => [
            'name' => 'Name der Serie',
            'name_placeholder' => 'Reitschule Mo. 17:00',
            'type' => 'Typ',
            'starts_time' => 'Startzeit',
            'duration_minutes' => 'Dauer (Min.)',
            'pattern' => 'Muster',
            'interval' => 'Alle',
            'days_of_week' => 'Wochentage',
            'recurrence_starts_on' => 'Von',
            'recurrence_ends_on' => 'Bis (optional)',
            'max_occurrences' => 'Maximale Termine',
            'max_occurrences_placeholder' => 'z. B. 26',
            'horse' => 'Pferd',
            'instructor' => 'Reitlehrer',
            'arena' => 'Reitplatz',
            'client' => 'Kunde',
            'title' => 'Titel des Termins',
            'price' => 'Preis',
            'is_active' => 'Aktive Serie',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'interval' => '1 = jeder, 2 = jeder zweite…',
            'recurrence_ends_on' => 'Leer = ohne Ende; der Expander erzeugt einmalig max. 365 Termine.',
            'max_occurrences' => 'Alternative zum Enddatum.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Typ',
            'pattern' => 'Muster',
            'starts_time' => 'Uhrzeit',
            'duration_minutes' => 'Min.',
            'recurrence_starts_on' => 'Von',
            'recurrence_ends_on' => 'Bis',
            'recurrence_ends_on_empty' => '— ohne Ende —',
            'occurrences_count' => 'Termine',
            'is_active' => 'Aktiv',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],

    'action' => [
        'expand' => [
            'label' => 'Termine generieren',
            'success_title' => 'Serie ausgerollt',
            'success_body' => ':count Termine erstellt.',
            'skipped' => ' Wegen Konflikt übersprungen: :list.',
        ],
        'cancel_series' => [
            'label' => 'Serie stornieren',
            'modal_heading' => 'Gesamte Serie stornieren',
            'modal_description' => 'Vergangene Termine bleiben erhalten, zukünftige werden storniert.',
            'success_title' => 'Serie storniert',
            'success_body' => ':count zukünftige Termine storniert.',
        ],
    ],
];
