<?php

declare(strict_types=1);

return [
    'login' => [
        'title' => 'Portail client — :tenant',
        'heading' => 'Portail client — :tenant',
        'intro' => 'Saisissez l’adresse e-mail utilisée pour vos confirmations de réservation. Vous recevrez un lien de connexion.',
        'email' => 'E-mail',
        'submit' => 'Envoyer le lien de connexion',
        'back' => '← Retour à la page de l’écurie',
    ],

    'sent' => [
        'title' => 'Consultez votre boîte de réception — :tenant',
        'heading' => 'Consultez votre boîte de réception',
        'body' => 'Si l’adresse <strong>:email</strong> est associée à un compte chez <strong>:tenant</strong>, nous vous avons envoyé un lien de connexion.',
        'ttl' => 'Le lien est valable 30 minutes.',
        'back' => '← Retour',
    ],

    'invalid' => [
        'title' => 'Lien inactif — :tenant',
        'heading' => 'Lien inactif',
        'body' => 'Ce lien de connexion a expiré ou a déjà été utilisé. Les liens sont à usage unique et valables 30 minutes.',
        'request_new' => 'Envoyer un nouveau lien',
    ],
];
