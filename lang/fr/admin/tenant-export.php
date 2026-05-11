<?php

declare(strict_types=1);

return [
    'action' => [
        'label' => 'Exporter les données (post-essai)',
        'modal_heading' => 'Export des données — :name',
        'modal_description' => 'Nous générons un ZIP contenant les clients, les chevaux, le calendrier (.ics), les factures et meta.json. Le fichier est téléchargé localement et supprimé après envoi — il ne reste pas sur le serveur.',
    ],
    'toast' => [
        'success_title' => 'Export prêt',
        'success_body' => 'Le fichier :file est prêt à être téléchargé.',
        'failure_title' => 'L’export a échoué',
    ],
];
