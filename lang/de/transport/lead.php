<?php

declare(strict_types=1);

return [
    'navigation' => 'Anfragen',

    'section' => [
        'customer' => 'Kunde',
        'route' => 'Strecke',
        'cargo' => 'Ladung',
        'lifecycle' => 'Lebenszyklus',
    ],

    'label' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'from' => 'Von',
        'to' => 'Nach',
        'pickup_voivodeship' => 'Woiwodschaft (Abholung)',
        'dropoff_voivodeship' => 'Woiwodschaft (Lieferung)',
        'preferred_date' => 'Datum',
        'preferred_time' => 'Uhrzeit',
        'horse_count' => 'Pferde',
        'flexible_date' => 'Datum flexibel',
        'notes' => 'Notizen des Kunden',
        'status' => 'Status',
        'mode' => 'Modus',
        'expires_at' => 'Läuft ab',
    ],

    'table' => [
        'column' => [
            'customer' => 'Kunde',
            'route' => 'Strecke',
            'preferred_date' => 'Datum',
            'horse_count' => 'Pferde',
            'status' => 'Status',
            'expires_at' => 'Läuft ab',
            'created_at' => 'Erhalten',
        ],
    ],

    'action' => [
        'respond' => 'Mit Angebot antworten',
    ],

    'notify' => [
        'respond_started' => 'Angebotsformular geöffnet',
        'respond_started_body' => 'Details ausfüllen und senden. Der Kunde sieht Ihr Angebot neben anderen.',
    ],
];
