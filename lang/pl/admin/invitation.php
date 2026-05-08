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
    ],
];
