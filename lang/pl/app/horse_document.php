<?php

declare(strict_types=1);

return [
    'uploaded_by' => [
        'stable' => 'Stajnia',
        'client' => 'Klient',
    ],

    'form' => [
        'label' => [
            'name' => 'Nazwa dokumentu',
            'name_placeholder' => 'np. Paszport Bucefała',
            'kind' => 'Kategoria',
            'description' => 'Opis (opcjonalnie)',
            'file' => 'Plik (max 25 MB)',
            'valid_from' => 'Ważny od (opcjonalne)',
            'valid_until' => 'Ważny do (opcjonalne)',
        ],
    ],

    'table' => [
        'column' => [
            'kind' => 'Kategoria',
            'name' => 'Nazwa',
            'original_name' => 'Plik',
            'size' => 'Rozmiar',
            'uploaded_by' => 'Wgrał',
            'valid_until' => 'Ważny do',
            'created_at' => 'Wgrany',
        ],
        'filter' => [
            'expiring_soon' => 'Wygasa w 30 dni',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Wgraj dokument',
            'no_file' => 'Brak pliku.',
            'failed' => 'Nie udało się wgrać',
        ],
        'download' => [
            'label' => 'Pobierz',
        ],
    ],
];
