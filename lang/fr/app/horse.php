<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Jument',
        'gelding' => 'Hongre',
        'stallion' => 'Étalon',
        'breeding_stallion' => 'Étalon reproducteur',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'characteristics' => 'Caractéristiques',
            'boarding' => 'Pension — prestations facturées',
            'boarding_description' => 'Sélectionnez les prestations du tarif applicables à ce cheval. Le client les verra dans le portail avec le montant mensuel estimé.',
            'notes' => 'Notes',
        ],
        'label' => [
            'name' => 'Nom',
            'owner' => 'Propriétaire',
            'owner_placeholder' => '— écurie —',
            'box' => 'Box',
            'box_placeholder' => '— non attribué —',
            'microchip' => 'Puce électronique',
            'passport_number' => 'N° de passeport',
            'ueln' => 'UELN',
            'sex' => 'Sexe',
            'breed' => 'Race',
            'color' => 'Robe',
            'birth_date' => 'Date de naissance',
            'boarding_services' => 'Prestations du tarif',
        ],
        'helper' => [
            'box' => 'Changer de box enregistre une entrée dans « Boxes → Historique des attributions ».',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Vous configurez le tarif dans « Écurie → Tarifs de pension ». Les surcharges de prix par cheval (par exemple une remise) y sont définies manuellement après création de l’entrée.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom',
            'breed' => 'Race',
            'sex' => 'Sexe',
            'color' => 'Robe',
            'birth_date' => 'Naissance',
            'owner' => 'Propriétaire',
            'owner_placeholder' => '— écurie —',
            'created_at' => 'Ajouté le',
        ],
    ],
];
