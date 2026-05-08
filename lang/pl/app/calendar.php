<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Czas i typ',
            'resources' => 'Zasoby',
            'details' => 'Szczegóły',
        ],
        'label' => [
            'type' => 'Typ',
            'starts_at' => 'Początek',
            'ends_at' => 'Koniec',
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'arena' => 'Ujeżdżalnia',
            'client' => 'Klient',
            'title' => 'Tytuł (dla wydarzeń / blokad)',
            'status' => 'Status',
            'price' => 'Cena',
            'notes' => 'Notatki',
        ],
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Początek',
            'ends_at' => 'Koniec',
            'type' => 'Typ',
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'arena' => 'Ujeżdżalnia',
            'client' => 'Klient',
            'status' => 'Status',
        ],
        'filter' => [
            'horse' => 'Koń',
            'instructor' => 'Instruktor',
            'upcoming' => 'Tylko nadchodzące',
        ],
    ],
];
