<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'pass' => 'Abonnement',
        ],
        'label' => [
            'client' => 'Client',
            'name' => 'Nom',
            'name_placeholder' => 'Carnet de 8 séances',
            'total_uses' => 'Nombre de séances',
            'remaining_uses' => 'Restantes',
            'valid_from' => 'Valide à partir du',
            'valid_until' => 'Valide jusqu’au',
            'price' => 'Prix de l’abonnement',
            'cancellation_policy_hours' => 'Politique d’annulation (h)',
            'cancellation_policy_placeholder' => 'utiliser la valeur par défaut de l’écurie',
            'status' => 'Statut',
            'notes' => 'Notes',
        ],
        'helper' => [
            'remaining_uses' => 'Mis à jour automatiquement par le système ; ne le modifiez manuellement qu’en cas exceptionnel.',
            'cancellation_policy_hours' => 'Annulation X heures avant la séance = sans frais (l’abonnement est recrédité).',
        ],
    ],

    'table' => [
        'column' => [
            'client' => 'Client',
            'name' => 'Abonnement',
            'remaining_uses' => 'Restantes',
            'status' => 'Statut',
            'valid_until' => 'Valide jusqu’au',
            'price' => 'Prix',
            'cancellation_policy' => 'Annulation',
            'cancellation_policy_default' => 'selon les paramètres de l’écurie',
            'created_at' => 'Émis le',
        ],
        'filter' => [
            'client' => 'Client',
        ],
    ],
];
