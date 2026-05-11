<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'entry' => 'Entrée',
            'details' => 'Détails',
        ],
        'label' => [
            'horse' => 'Cheval',
            'template' => 'Modèle de soin',
            'template_placeholder' => '— sélectionnez un modèle (optionnel) —',
            'type' => 'Type',
            'performed_at' => 'Date du soin',
            'performed_by' => 'Effectué par (vétérinaire / maréchal-ferrant / entreprise)',
            'performed_by_placeholder' => 'par exemple assistant du maréchal-ferrant (si différent de la personne sélectionnée ci-dessus)',
            'specialist' => 'Spécialiste',
            'specialist_placeholder' => '— sélectionner dans la liste des spécialistes —',
            'summary' => 'Description courte',
            'summary_placeholder' => 'Vaccination tétanos + grippe',
            'next_due_at' => 'Prochain soin',
            'cost' => 'Coût',
            'details' => 'Notes / médicaments / recommandations',
        ],
        'helper' => [
            'template' => 'Choisir un modèle remplit automatiquement le type, la description et la prochaine échéance suggérée.',
            'next_due_at' => 'Cela déclenchera une alerte sur le tableau de bord.',
            'specialist' => 'La liste est filtrée par type d’entrée — maréchaux-ferrants pour « Maréchal-ferrant », vétérinaires pour les autres types. Configurez la liste dans Écurie → Spécialistes.',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Date',
            'horse' => 'Cheval',
            'type' => 'Type',
            'summary' => 'Description',
            'performed_by' => 'Effectué par',
            'next_due_at' => 'Prochaine échéance',
            'cost' => 'Coût',
        ],
        'filter' => [
            'horse' => 'Cheval',
            'overdue' => 'En retard (prochaine échéance dépassée)',
            'due_30' => 'Prochain dans les 30 jours',
        ],
    ],
];
