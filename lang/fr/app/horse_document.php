<?php

declare(strict_types=1);

return [
    'uploaded_by' => [
        'stable' => 'Écurie',
        'client' => 'Client',
    ],

    'form' => [
        'label' => [
            'name' => 'Nom du document',
            'name_placeholder' => 'par exemple Passeport de Bucéphale',
            'kind' => 'Catégorie',
            'description' => 'Description (optionnelle)',
            'file' => 'Fichier (max 25 Mo)',
            'valid_from' => 'Valide à partir du (optionnel)',
            'valid_until' => 'Valide jusqu’au (optionnel)',
        ],
    ],

    'table' => [
        'column' => [
            'kind' => 'Catégorie',
            'name' => 'Nom',
            'original_name' => 'Fichier',
            'size' => 'Taille',
            'uploaded_by' => 'Téléversé par',
            'valid_until' => 'Valide jusqu’au',
            'created_at' => 'Téléversé le',
        ],
        'filter' => [
            'expiring_soon' => 'Expire sous 30 jours',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Téléverser un document',
            'no_file' => 'Aucun fichier.',
            'failed' => 'Le téléversement a échoué',
        ],
        'download' => [
            'label' => 'Télécharger',
        ],
    ],
];
