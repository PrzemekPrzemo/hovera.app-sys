<?php

declare(strict_types=1);

return [
    'navigation' => 'Demandes',

    'section' => [
        'customer' => 'Client',
        'route' => 'Itinéraire',
        'cargo' => 'Chargement',
        'lifecycle' => 'Cycle de vie',
    ],

    'label' => [
        'name' => 'Nom complet',
        'email' => 'E-mail',
        'phone' => 'Téléphone',
        'from' => 'De',
        'to' => 'Vers',
        'pickup_voivodeship' => 'Voïvodie (prise en charge)',
        'dropoff_voivodeship' => 'Voïvodie (livraison)',
        'preferred_date' => 'Date',
        'preferred_time' => 'Heure',
        'horse_count' => 'Chevaux',
        'flexible_date' => 'Date flexible',
        'notes' => 'Notes du client',
        'status' => 'Statut',
        'mode' => 'Mode',
        'expires_at' => 'Expire',
    ],

    'table' => [
        'column' => [
            'customer' => 'Client',
            'route' => 'Itinéraire',
            'preferred_date' => 'Date',
            'horse_count' => 'Chevaux',
            'status' => 'Statut',
            'expires_at' => 'Expire',
            'created_at' => 'Reçue',
        ],
    ],

    'action' => [
        'respond' => 'Répondre avec une offre',
        'open_in_calculator' => 'Ouvrir dans le calculateur',
    ],

    'notify' => [
        'respond_started' => 'Formulaire d’offre ouvert',
        'respond_started_body' => 'Remplissez les détails et envoyez. Le client verra votre offre.',
    ],
];
