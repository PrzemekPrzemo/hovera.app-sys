<?php

declare(strict_types=1);

return [
    'navigation' => 'Audit log',
    'model' => 'wpis audytu',
    'model_plural' => 'audit log',

    'column' => [
        'timestamp' => 'Czas',
        'actor' => 'Wykonawca',
        'tenant' => 'Tenant',
        'action' => 'Akcja',
        'target' => 'Cel',
        'target_type' => 'Typ celu',
        'target_id' => 'ID celu',
        'ip' => 'IP',
        'user_agent' => 'User-Agent',
        'payload' => 'Payload (JSON)',
    ],

    'filter' => [
        'actor' => 'Wykonawca (master admin)',
        'tenant' => 'Tenant',
        'from' => 'Od',
        'until' => 'Do',
    ],
];
