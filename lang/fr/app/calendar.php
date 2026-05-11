<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Horaire et type',
            'resources' => 'Ressources',
            'details' => 'Détails',
            'participants' => 'Participants au cours collectif',
            'participants_description' => 'Chaque participant = client + cheval (optionnel). Après le cours, marquez la présence pour chaque participant.',
        ],
        'label' => [
            'type' => 'Type',
            'starts_at' => 'Début',
            'ends_at' => 'Fin',
            'horse' => 'Cheval',
            'instructor' => 'Moniteur',
            'arena' => 'Manège',
            'client' => 'Client',
            'title' => 'Intitulé (pour événements / blocages)',
            'status' => 'Statut',
            'price' => 'Prix',
            'notes' => 'Notes',
            'participants' => 'Participants',
            'participant_client' => 'Client',
            'participant_horse' => 'Cheval (optionnel)',
            'participant_horse_placeholder' => '— monte son propre cheval / à attribuer plus tard —',
            'participant_attendance' => 'Présence',
            'participant_notes' => 'Notes (par exemple « première leçon »)',
        ],
    ],

    'attendance' => [
        'expected' => 'Attendu',
        'present' => 'Présent',
        'absent' => 'Absent',
        'late' => 'En retard',
    ],

    'actions' => [
        'add_participant' => '+ Ajouter un participant',
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Début',
            'ends_at' => 'Fin',
            'type' => 'Type',
            'horse' => 'Cheval',
            'instructor' => 'Moniteur',
            'arena' => 'Manège',
            'client' => 'Client',
            'status' => 'Statut',
        ],
        'participant_count' => '{0} aucun participant|{1} 👥 :count participant|[2,*] 👥 :count participants',
        'filter' => [
            'horse' => 'Cheval',
            'instructor' => 'Moniteur',
            'upcoming' => 'Uniquement à venir',
        ],
    ],
];
