<?php

declare(strict_types=1);

return [
    'section' => [
        'personal' => 'Données personnelles',
        'contact' => 'Contact',
        'license' => 'Permis de conduire',
        'qualifications' => 'Qualifications supplémentaires',
        'qualifications_description' => 'Certificat de transport d’animaux (directive UE). ADR — pour les matières dangereuses.',
        'other' => 'Autres',
    ],

    'form' => [
        'label' => [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'date_of_birth' => 'Date de naissance',
            'email' => 'Email',
            'phone' => 'Téléphone',
            'license_number' => 'Numéro de permis',
            'license_categories' => 'Catégories',
            'license_expires_at' => 'Valide jusqu’au',
            'has_animal_transport_cert' => 'Certificat de transport d’animaux',
            'animal_transport_cert_expires_at' => 'Valide jusqu’au',
            'has_adr' => 'ADR',
            'adr_expires_at' => 'Valide jusqu’au',
            'hire_date' => 'Date d’embauche',
            'is_active' => 'Actif',
            'sort_order' => 'Ordre',
            'notes' => 'Notes',
        ],
        'helper' => [
            'email' => 'Adresse utilisée pour les notifications de mission.',
        ],
    ],

    'table' => [
        'column' => [
            'full_name' => 'Chauffeur',
            'phone' => 'Téléphone',
            'email' => 'Email',
            'license_expires_at' => 'Permis jusqu’à',
            'has_animal_transport_cert' => 'Transp. animaux',
            'is_active' => 'Actif',
        ],
    ],

    'filter' => [
        'license_expiring_soon' => 'Permis expire dans 30 jours',
    ],
];
