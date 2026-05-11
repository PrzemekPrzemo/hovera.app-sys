<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'role' => 'Rolle',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'E-Mail',
            'name' => 'Vor- und Nachname',
            'role' => 'Rolle',
            'joined_at' => 'Beigetreten',
            'revoked_at' => 'Widerrufen',
        ],
    ],

    'action' => [
        'add' => [
            'label' => 'Mitarbeiter hinzufügen',
        ],
        'send_password_reset' => [
            'label' => 'Passwort-Reset-Link senden',
            'modal_description' => 'Wir senden eine E-Mail mit dem Passwort-Reset-Link an :email. Der Link läuft nach 60 Minuten ab.',
            'success_title' => 'Passwort-Reset-Link gesendet',
            'success_body' => 'E-Mail an :email gesendet. Der Mitarbeiter sollte den Posteingang (auch SPAM) prüfen.',
            'failure_title' => 'Link konnte nicht gesendet werden',
            'failure_no_email' => 'Der Mitarbeiter hat keine E-Mail-Adresse im Profil.',
        ],
    ],
];
