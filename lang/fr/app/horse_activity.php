<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Type',
            'performed_at' => 'Quand',
            'performed_by' => 'Effectué par (prénom du palefrenier)',
            'performed_by_placeholder' => 'par exemple Tom (si différent du spécialiste sélectionné)',
            'specialist' => 'Spécialiste (maréchal-ferrant / vétérinaire)',
            'specialist_placeholder' => '— si l’intervention a été réalisée par un spécialiste, sélectionnez-le dans la liste —',
            'cost' => 'Coût supplémentaire (optionnel)',
            'summary' => 'Description courte',
            'summary_placeholder' => 'par exemple « Sortie paddock 9 h-12 h, paddock est »',
            'details' => 'Notes',
        ],
        'helper' => [
            'cost' => 'À renseigner uniquement si l’activité a généré un coût en dehors du forfait (par exemple foin supplémentaire, transport).',
            'specialist' => 'Liste de tous les spécialistes actifs (maréchaux-ferrants + vétérinaires). Configurez-la dans Écurie → Spécialistes.',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Date',
            'type' => 'Type',
            'summary' => 'Description',
            'performed_by' => 'Effectué par',
            'cost' => 'Coût',
        ],
    ],
];
