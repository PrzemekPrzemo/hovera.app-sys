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
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Collez l’URL du profil du cheval depuis LiveJumping.com — nous afficherons le palmarès et les prochains départs.',
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
            'livejumping_profile_url' => 'URL du profil LiveJumping',
            'livejumping_palmares' => 'Palmarès',
        ],
        'helper' => [
            'box' => 'Changer de box enregistre une entrée dans « Boxes → Historique des attributions ».',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Vous configurez le tarif dans « Écurie → Tarifs de pension ». Les surcharges de prix par cheval (par exemple une remise) y sont définies manuellement après création de l’entrée.',
            'livejumping_profile_url' => 'Copiez l’URL de la page de profil depuis livejumping.com — par exemple https://livejumping.com/horse/12345/romeo',
            'livejumping_no_profile' => 'Collez une URL de profil LJ ci-dessus pour voir le palmarès.',
            'livejumping_fetch_failed' => 'Impossible de récupérer les données depuis LiveJumping (vérifiez l’URL ou réessayez plus tard).',
        ],
        'stats' => [
            'starts' => 'Départs',
            'wins' => 'Victoires',
            'placings' => 'Places de tête',
            'ranking_points' => 'Points de classement',
            'recent_results' => 'Résultats récents',
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
