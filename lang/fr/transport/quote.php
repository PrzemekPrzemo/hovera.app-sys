<?php

declare(strict_types=1);

return [
    'section' => [
        'header' => 'En-tête',
        'customer' => 'Client',
        'route' => 'Itinéraire',
        'resources' => 'Ressources (optionnel)',
        'pricing' => 'Tarification',
        'terms' => 'Conditions et notes',
    ],

    'form' => [
        'label' => [
            'number' => 'Numéro',
            'status' => 'Statut',
            'valid_until' => 'Valide jusqu’au',
            'customer_name' => 'Nom complet',
            'customer_email' => 'Email',
            'customer_phone' => 'Téléphone',
            'customer_company' => 'Société',
            'customer_tax_id' => 'N° TVA',
            'customer_address' => 'Adresse de facturation',
            'pickup_address' => 'Adresse de prise en charge',
            'dropoff_address' => 'Adresse de livraison',
            'preferred_date' => 'Date',
            'preferred_time' => 'Heure',
            'round_trip' => 'Aller-retour',
            'loaded' => 'Chargé (avec cheval)',
            'vehicle' => 'Véhicule',
            'driver' => 'Chauffeur',
            'distance_km' => 'Distance',
            'rate_per_km' => 'Tarif',
            'duration_seconds' => 'Durée (s)',
            'base_cost' => 'Coût de base',
            'fuel_surcharge' => 'Surcoût carburant',
            'minimum_adjustment' => 'Ajustement minimum',
            'net_total' => 'HT',
            'vat_rate' => 'Taux de TVA',
            'vat_amount' => 'Montant TVA',
            'gross_total' => 'TTC',
            'currency' => 'Devise',
            'routing_provider' => 'Source itinéraire',
            'terms' => 'Conditions commerciales',
            'notes' => 'Notes internes',
        ],
        'helper' => [
            'terms' => 'Visible par le client sur le devis / PDF.',
            'notes' => 'Notes internes — pas partagées avec le client.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numéro',
            'customer' => 'Client',
            'route' => 'Itinéraire',
            'preferred_date' => 'Date',
            'gross_total' => 'TTC',
            'status' => 'Statut',
            'created_at' => 'Créé',
        ],
    ],

    'action' => [
        'send' => 'Envoyer au client',
        'withdraw' => 'Retirer le devis',
    ],

    'notify' => [
        'sent' => 'Devis envoyé',
        'withdrawn' => 'Devis retiré',
    ],
];
