<?php

declare(strict_types=1);

return [
    'types' => [
        'vet' => 'Tierarzt',
        'farrier' => 'Hufschmied',
    ],
    'types_short' => [
        'vet' => 'Tierarzt',
        'farrier' => 'Hufschmied',
    ],

    'form' => [
        'section' => [
            'data' => 'Spezialisten-Daten',
            'access' => 'Systemkonto (optional)',
            'access_description' => 'Verknüpfen Sie mit einem Mitarbeiterkonto des Reitstalls — nur wenn der Spezialist sich im Panel anmeldet und die Ansicht „Meine Aufgaben" sehen soll. Die meisten Hufschmiede / Tierärzte sind externe Auftragnehmer — leer lassen.',
        ],
        'label' => [
            'type' => 'Fachrichtung',
            'name' => 'Vor- und Nachname',
            'phone' => 'Telefon',
            'color' => 'Farbe im Kalender',
            'central_user' => 'Mit Mitarbeiter verknüpfen',
            'central_user_placeholder' => '— ohne Konto —',
            'is_active' => 'Aktiv',
            'sort_order' => 'Reihenfolge',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'central_user' => 'Die Liste enthält nur aktive Reitstall-Mitglieder. Die Auswahl ermöglicht später die Ansicht „Meine Aufgaben" für den angemeldeten Spezialisten.',
        ],
    ],

    'table' => [
        'column' => [
            'type' => 'Fachrichtung',
            'name' => 'Vor- und Nachname',
            'phone' => 'Telefon',
            'central_user' => 'Konto',
            'is_active' => 'Aktiv',
        ],
        'filter' => [
            'type' => 'Fachrichtung',
            'has_account' => 'Mit Systemkonto',
        ],
        'has_account_yes' => 'Ja',
        'has_account_no' => '—',
    ],
];
