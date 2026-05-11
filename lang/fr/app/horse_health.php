<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Type',
            'performed_at' => 'Date du soin',
            'summary' => 'Description courte',
            'performed_by' => 'Effectué par',
            'performed_by_placeholder' => 'par exemple assistant (si différent du spécialiste)',
            'specialist' => 'Spécialiste',
            'specialist_placeholder' => '— sélectionner dans la liste —',
            'next_due_at' => 'Prochain soin',
            'cost' => 'Coût',
            'details' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Date',
            'type' => 'Type',
            'summary' => 'Description',
            'performed_by' => 'Effectué par',
            'next_due_at' => 'Prochaine échéance',
        ],
    ],
];
