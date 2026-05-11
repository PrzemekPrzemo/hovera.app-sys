<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'measured_at' => 'Date de mesure',
            'weight_kg' => 'Poids',
            'girth_cm' => 'Tour de poitrine (passage de sangle)',
            'notes' => 'Notes',
        ],
        'helper' => [
            'girth_cm' => 'Optionnel — utile en l’absence de pèse-cheval (formule tour de poitrine² × longueur).',
        ],
    ],
    'table' => [
        'column' => [
            'measured_at' => 'Date',
            'weight_kg' => 'Poids',
            'girth_cm' => 'Tour',
            'trend' => 'Évolution',
            'notes' => 'Notes',
        ],
    ],
];
