<?php

declare(strict_types=1);

return [
    'table' => [
        'column' => [
            'tenant' => 'Stajnia',
            'role' => 'Rola',
            'status' => 'Status',
            'invited_by' => 'Zapraszający',
            'expires_at' => 'Wygasa',
            'accepted_at' => 'Zaakceptowane',
            'created_at' => 'Wysłane',
        ],
        'status' => [
            'pending' => 'Oczekuje',
            'accepted' => 'Zaakceptowane',
            'expired' => 'Wygasłe',
        ],
        'filter' => [
            'only_pending' => 'Tylko oczekujące',
            'expired' => 'Tylko wygasłe',
            'accepted' => 'Tylko zaakceptowane',
            'tenant' => 'Stajnia',
        ],
    ],
    'action' => [
        'resend' => [
            'label' => 'Wyślij ponownie',
            'success' => 'Zaproszenie wysłane ponownie',
        ],
        'revoke' => [
            'label' => 'Unieważnij',
            'success' => 'Zaproszenie unieważnione',
        ],
        'show_url' => [
            'label' => 'Pokaż link logowania',
            'modal_heading' => 'Link logowania dla :email',
            'modal_description' => 'Każde wywołanie generuje NOWY token (poprzedni jest unieważniany). Token surowy nie jest zapisany w DB — pojawia się tylko tutaj raz.',
            'success_title' => 'Link wygenerowany — skopiuj poniżej:',
        ],
        'resend_email' => [
            'label' => 'Wyślij mailem',
            'success_title' => 'Zaproszenie wysłane na :email',
            'success_body' => "Link (do skopiowania jeśli mail nie dojdzie):\n:url",
        ],
    ],
];
