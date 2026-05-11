<?php

declare(strict_types=1);

return [
    'table' => [
        'column' => [
            'tenant' => 'Reitstall',
            'role' => 'Rolle',
            'status' => 'Status',
            'invited_by' => 'Einladender',
            'expires_at' => 'Läuft ab',
            'accepted_at' => 'Angenommen',
            'created_at' => 'Gesendet',
        ],
        'status' => [
            'pending' => 'Ausstehend',
            'accepted' => 'Angenommen',
            'expired' => 'Abgelaufen',
        ],
        'filter' => [
            'only_pending' => 'Nur ausstehend',
            'expired' => 'Nur abgelaufen',
            'accepted' => 'Nur angenommen',
            'tenant' => 'Reitstall',
        ],
    ],
    'action' => [
        'resend' => [
            'label' => 'Erneut senden',
            'success' => 'Einladung erneut gesendet',
        ],
        'revoke' => [
            'label' => 'Widerrufen',
            'success' => 'Einladung widerrufen',
        ],
        'show_url' => [
            'label' => 'Anmeldelink anzeigen',
            'modal_heading' => 'Anmeldelink für :email',
            'modal_description' => 'Jeder Aufruf generiert einen NEUEN Token (der vorherige wird widerrufen). Der Rohtoken wird nicht in der DB gespeichert — er erscheint nur hier einmal.',
            'success_title' => 'Link generiert — unten kopieren:',
        ],
        'resend_email' => [
            'label' => 'Per E-Mail senden',
            'success_title' => 'Einladung an :email gesendet',
            'success_body' => "Link (zum Kopieren, falls die Mail nicht ankommt):\n:url",
        ],
    ],
];
