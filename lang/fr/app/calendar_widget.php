<?php

declare(strict_types=1);

return [
    'action' => [
        'create' => [
            'label' => 'Ajouter une réservation',
            'modal_heading' => 'Nouvelle réservation',
            'success' => 'Réservation ajoutée',
            'conflict_title' => 'Conflit',
        ],
        'edit' => [
            'label' => 'Modifier la réservation',
            'modal_heading' => 'Modifier la réservation',
            'success' => 'Réservation mise à jour',
        ],
        'delete' => [
            'label' => 'Supprimer la réservation',
            'success' => 'Réservation supprimée',
        ],
    ],

    'form' => [
        'label' => [
            'type' => 'Type',
            'starts_at' => 'Début',
            'ends_at' => 'Fin',
            'horse' => 'Cheval',
            'instructor' => 'Moniteur',
            'arena' => 'Manège',
            'client' => 'Client',
            'title' => 'Intitulé',
            'status' => 'Statut',
            'notes' => 'Notes',
        ],
    ],
];
