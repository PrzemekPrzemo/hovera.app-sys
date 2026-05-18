<?php

declare(strict_types=1);

return [
    'navigation' => 'Zapytania',

    'section' => [
        'customer' => 'Klient',
        'route' => 'Trasa',
        'cargo' => 'Ładunek',
        'lifecycle' => 'Lifecycle',
    ],

    'label' => [
        'name' => 'Imię i nazwisko',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'from' => 'Skąd',
        'to' => 'Dokąd',
        'pickup_voivodeship' => 'Województwo (odbiór)',
        'dropoff_voivodeship' => 'Województwo (dostarczenie)',
        'preferred_date' => 'Data',
        'preferred_time' => 'Godzina',
        'horse_count' => 'Liczba koni',
        'flexible_date' => 'Data elastyczna',
        'notes' => 'Notatki klienta',
        'status' => 'Status',
        'mode' => 'Tryb',
        'expires_at' => 'Wygasa',
    ],

    'table' => [
        'column' => [
            'customer' => 'Klient',
            'route' => 'Trasa',
            'preferred_date' => 'Data',
            'horse_count' => 'Koni',
            'status' => 'Status',
            'expires_at' => 'Wygasa',
            'created_at' => 'Otrzymane',
        ],
    ],

    'action' => [
        'respond' => 'Odpowiedz ofertą',
    ],

    'notify' => [
        'respond_started' => 'Otwarto formularz oferty',
        'respond_started_body' => 'Wypełnij szczegóły i wyślij. Klient zobaczy Twoją ofertę razem z innymi odpowiedziami.',
    ],
];
