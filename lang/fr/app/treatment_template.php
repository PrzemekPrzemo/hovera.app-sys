<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'template' => 'Modèle de soin',
        ],
        'label' => [
            'name' => 'Nom',
            'type' => 'Type de soin',
            'interval_days' => 'Fréquence (jours)',
            'sort_order' => 'Ordre',
            'default_summary' => 'Description par défaut',
            'default_notes' => 'Notes par défaut',
            'is_active' => 'Actif',
        ],
        'helper' => [
            'interval_days' => 'Nombre de jours jusqu’à la prochaine visite. Vide = soin ponctuel sans nouvelle échéance.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Nom',
            'type' => 'Type',
            'interval' => 'Tous les',
            'is_active' => 'Actif',
        ],
        'days' => 'jours',
    ],
];
