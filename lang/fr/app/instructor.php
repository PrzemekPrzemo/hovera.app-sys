<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Informations du moniteur',
        ],
        'label' => [
            'name' => 'Nom et prénom',
            'phone' => 'Téléphone',
            'hourly_rate' => 'Tarif horaire',
            'color' => 'Couleur dans le calendrier',
            'is_active' => 'Actif',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nom et prénom',
            'phone' => 'Téléphone',
            'hourly_rate' => 'Tarif',
            'color' => 'Couleur',
            'is_active' => 'Actif',
        ],
        'filter' => [
            'status' => 'Statut',
        ],
    ],

    'actions' => [
        'ics_url' => 'Calendrier .ics',
    ],
    'ics_modal' => [
        'heading' => 'Calendrier du moniteur :name',
        'description' => 'Copiez l’URL et collez-la dans Google Calendar / Outlook / Apple Calendar via « Ajouter un calendrier par URL ». Les leçons apparaîtront automatiquement et seront synchronisées toutes les quelques heures.',
        'url_label' => 'URL du flux (abonnement)',
        'howto' => 'Google Calendar → « Autres calendriers » → « + → À partir de l’URL » → collez l’URL. Outlook → « Ajouter un calendrier → S’abonner depuis le web ». Apple → File → New Calendar Subscription.',
        'token_ensured' => 'URL prête',
        'close' => 'Fermer',
    ],
];
