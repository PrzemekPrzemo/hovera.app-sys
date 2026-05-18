<?php

declare(strict_types=1);

return [
    'navigation' => 'Zone de service',
    'title' => 'Voïvodies de service',

    'section' => [
        'heading' => 'Choisir les voïvodies',
        'description' => 'Cochez celles où vous opérez. En mode broadcast, vous recevrez des demandes de ces voïvodies et des voisines (carte d’adjacence).',
    ],

    'form' => [
        'label' => [
            'voivodeships' => 'Voïvodies',
        ],
    ],

    'action' => [
        'save' => 'Enregistrer',
    ],

    'notify' => [
        'saved' => 'Zone de service mise à jour',
        'saved_body' => 'Sélectionnées : :direct voïvodies, couverture totale avec adjacence : :effective.',
    ],
];
