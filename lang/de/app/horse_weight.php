<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'measured_at' => 'Messdatum',
            'weight_kg' => 'Gewicht',
            'girth_cm' => 'Brustumfang (Girth)',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'girth_cm' => 'Optional — hilfreich, wenn keine Waage vorhanden ist (Formel Umfang² × Länge).',
        ],
    ],
    'table' => [
        'column' => [
            'measured_at' => 'Datum',
            'weight_kg' => 'Gewicht',
            'girth_cm' => 'Umfang',
            'trend' => 'Veränderung',
            'notes' => 'Notizen',
        ],
    ],
];
