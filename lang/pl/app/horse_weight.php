<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'measured_at' => 'Data pomiaru',
            'weight_kg' => 'Waga',
            'girth_cm' => 'Obwód klatki (girth)',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'girth_cm' => 'Opcjonalnie — pomocne, gdy nie ma wagi (formuła obwód² × długość).',
        ],
    ],
    'table' => [
        'column' => [
            'measured_at' => 'Data',
            'weight_kg' => 'Waga',
            'girth_cm' => 'Obwód',
            'trend' => 'Zmiana',
            'notes' => 'Notatki',
        ],
    ],
];
