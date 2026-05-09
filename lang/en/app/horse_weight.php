<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'measured_at' => 'Date measured',
            'weight_kg' => 'Weight',
            'girth_cm' => 'Heart girth',
            'notes' => 'Notes',
        ],
        'helper' => [
            'girth_cm' => 'Optional — useful when no scale is available (formula: girth² × length).',
        ],
    ],
    'table' => [
        'column' => [
            'measured_at' => 'Date',
            'weight_kg' => 'Weight',
            'girth_cm' => 'Girth',
            'trend' => 'Change',
            'notes' => 'Notes',
        ],
    ],
];
