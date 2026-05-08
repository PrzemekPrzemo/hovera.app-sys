<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Dane instruktora',
        ],
        'label' => [
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
            'hourly_rate' => 'Stawka za godzinę',
            'color' => 'Kolor w kalendarzu',
            'is_active' => 'Aktywny',
            'notes' => 'Notatki',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
            'hourly_rate' => 'Stawka',
            'color' => 'Kolor',
            'is_active' => 'Aktywny',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],
];
