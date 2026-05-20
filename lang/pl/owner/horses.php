<?php

declare(strict_types=1);

return [
    'navigation' => 'Moje konie',

    'model' => [
        'singular' => 'koń',
        'plural' => 'konie',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'notes' => 'Notatki',
        ],
        'label' => [
            'name' => 'Imię',
            'breed' => 'Rasa',
            'birth_date' => 'Data urodzenia',
            'sex' => 'Płeć',
            'color' => 'Maść',
            'passport_number' => 'Numer paszportu',
            'microchip' => 'Numer chip',
            'notes' => 'Notatki',
        ],
    ],

    'table' => [
        'name' => 'Imię',
        'breed' => 'Rasa',
        'birth_date' => 'Data urodzenia',
        'sex' => 'Płeć',
        'passport_number' => 'Paszport',
    ],

    'sex' => [
        'mare' => 'Klacz',
        'stallion' => 'Ogier',
        'gelding' => 'Wałach',
        'filly' => 'Klaczka',
        'colt' => 'Ogierek',
        'foal' => 'Źrebię',
    ],

    'empty' => [
        'heading' => 'Brak koni w kartotece',
        'description' => 'Dodaj swojego pierwszego konia, by szybciej składać zamówienia transportu.',
    ],

    'action' => [
        'order_transport' => 'Zamów transport tego konia',
    ],
];
