<?php

declare(strict_types=1);

return [
    'navigation' => 'POI library',

    'model' => [
        'singular' => 'POI',
        'plural' => 'POI library',
    ],

    'section' => [
        'basic' => 'Podstawowe dane',
        'metadata' => 'Metadane',
    ],

    'form' => [
        'label' => [
            'name' => 'Nazwa',
            'kind' => 'Typ',
            'address' => 'Adres',
            'notes' => 'Notatki',
            'is_active' => 'Aktywny',
            'sort_order' => 'Kolejność',
        ],
        'helper' => [
            'address' => 'Geokodujemy adres przy zapisie — dzięki temu można używać POI w wycenach bez ponownego wpisywania współrzędnych.',
        ],
    ],

    'kind' => [
        'base' => 'Baza transportera',
        'stable' => 'Stajnia',
        'parking' => 'Parking',
        'fuel' => 'Stacja paliw',
        'other' => 'Inne',
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'kind' => 'Typ',
            'address' => 'Adres',
            'is_active' => 'Aktywne',
        ],
    ],

    'empty' => [
        'heading' => 'Biblioteka POI jest pusta',
        'description' => 'Dodaj swoje bazy, stajnie pensjonarskie, parkingi truck-friendly — będą podpowiadane przy tworzeniu wycen.',
    ],

    'notify' => [
        'geocoding_failed_title' => 'Nie rozpoznaliśmy adresu POI',
    ],
];
