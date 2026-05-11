<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'role' => 'Rôle',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'E-mail',
            'name' => 'Nom et prénom',
            'role' => 'Rôle',
            'joined_at' => 'A rejoint le',
            'revoked_at' => 'Révoqué le',
        ],
    ],

    'action' => [
        'add' => [
            'label' => 'Ajouter un collaborateur',
        ],
        'send_password_reset' => [
            'label' => 'Envoyer un lien de réinitialisation du mot de passe',
            'modal_description' => 'Nous enverrons un e-mail avec un lien de réinitialisation du mot de passe à :email. Le lien expire après 60 minutes.',
            'success_title' => 'Lien de réinitialisation envoyé',
            'success_body' => 'E-mail envoyé à :email. Le collaborateur doit vérifier sa boîte de réception (ainsi que le dossier des spams).',
            'failure_title' => 'Impossible d’envoyer le lien',
            'failure_no_email' => 'Ce collaborateur n’a pas d’adresse e-mail dans son profil.',
        ],
    ],
];
