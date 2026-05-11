<?php

declare(strict_types=1);

return [
    'days_of_week' => [
        '1' => 'Lundi',
        '2' => 'Mardi',
        '3' => 'Mercredi',
        '4' => 'Jeudi',
        '5' => 'Vendredi',
        '6' => 'Samedi',
        '0' => 'Dimanche',
    ],

    'form' => [
        'section' => [
            'basic' => 'Informations de base',
            'recurrence' => 'Récurrence',
            'default_resources' => 'Ressources par défaut',
            'details' => 'Détails',
        ],
        'label' => [
            'name' => 'Nom de la série',
            'name_placeholder' => 'École du lundi 17h00',
            'type' => 'Type',
            'starts_time' => 'Heure de début',
            'duration_minutes' => 'Durée (min)',
            'pattern' => 'Schéma',
            'interval' => 'Tous les',
            'days_of_week' => 'Jours de la semaine',
            'recurrence_starts_on' => 'À partir du',
            'recurrence_ends_on' => 'Jusqu’au (optionnel)',
            'max_occurrences' => 'Limite d’occurrences',
            'max_occurrences_placeholder' => 'par exemple 26',
            'horse' => 'Cheval',
            'instructor' => 'Moniteur',
            'arena' => 'Manège',
            'client' => 'Client',
            'title' => 'Intitulé de la séance',
            'price' => 'Prix',
            'is_active' => 'Série active',
            'notes' => 'Notes',
        ],
        'helper' => [
            'interval' => '1 = chaque, 2 = un sur deux…',
            'recurrence_ends_on' => 'Vide = sans fin ; l’expanseur génère au maximum 365 occurrences en une fois.',
            'max_occurrences' => 'Alternative à la date de fin.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom',
            'type' => 'Type',
            'pattern' => 'Schéma',
            'starts_time' => 'Heure',
            'duration_minutes' => 'Min',
            'recurrence_starts_on' => 'À partir du',
            'recurrence_ends_on' => 'Jusqu’au',
            'recurrence_ends_on_empty' => '— sans fin —',
            'occurrences_count' => 'Occurrences',
            'is_active' => 'Active',
        ],
        'filter' => [
            'status' => 'Statut',
        ],
    ],

    'action' => [
        'expand' => [
            'label' => 'Générer les occurrences',
            'success_title' => 'Série déployée',
            'success_body' => ':count occurrences créées.',
            'skipped' => ' Ignorées pour cause de conflit : :list.',
        ],
        'cancel_series' => [
            'label' => 'Annuler la série',
            'modal_heading' => 'Annuler toute la série',
            'modal_description' => 'Les occurrences passées sont conservées, les futures sont annulées.',
            'success_title' => 'Série annulée',
            'success_body' => ':count occurrences futures annulées.',
        ],
    ],
];
