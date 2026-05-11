<?php

declare(strict_types=1);

return [
    'types' => [
        'vet' => 'Vétérinaire',
        'farrier' => 'Maréchal-ferrant',
    ],
    'types_short' => [
        'vet' => 'Vét.',
        'farrier' => 'Maréchal',
    ],

    'form' => [
        'section' => [
            'data' => 'Informations du spécialiste',
            'access' => 'Compte dans le système (optionnel)',
            'access_description' => 'À associer à un compte d’employé de l’écurie — uniquement si le spécialiste se connecte au panneau et doit voir « Mes tâches ». La plupart des maréchaux-ferrants et vétérinaires sont des prestataires externes — laissez vide.',
        ],
        'label' => [
            'type' => 'Spécialité',
            'name' => 'Nom et prénom',
            'phone' => 'Téléphone',
            'color' => 'Couleur dans le calendrier',
            'central_user' => 'Associer à un employé',
            'central_user_placeholder' => '— sans compte —',
            'is_active' => 'Actif',
            'sort_order' => 'Ordre',
            'notes' => 'Notes',
        ],
        'helper' => [
            'central_user' => 'La liste ne contient que les membres actifs de l’écurie. La sélection ici permettra d’activer ultérieurement la vue « Mes tâches » pour le spécialiste connecté.',
        ],
    ],

    'table' => [
        'column' => [
            'type' => 'Spécialité',
            'name' => 'Nom et prénom',
            'phone' => 'Téléphone',
            'central_user' => 'Compte',
            'is_active' => 'Actif',
        ],
        'filter' => [
            'type' => 'Spécialité',
            'has_account' => 'Avec compte dans le système',
        ],
        'has_account_yes' => 'Oui',
        'has_account_no' => '—',
    ],
];
