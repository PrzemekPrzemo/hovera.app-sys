<?php

declare(strict_types=1);

return [
    'navigation' => 'Audit log',
    'model' => 'audit entry',
    'model_plural' => 'audit log',

    'column' => [
        'timestamp' => 'Time',
        'actor' => 'Actor',
        'tenant' => 'Tenant',
        'action' => 'Action',
        'target' => 'Target',
        'target_type' => 'Target type',
        'target_id' => 'Target ID',
        'ip' => 'IP',
        'user_agent' => 'User-Agent',
        'payload' => 'Payload (JSON)',
    ],

    'filter' => [
        'actor' => 'Actor (master admin)',
        'tenant' => 'Tenant',
        'from' => 'From',
        'until' => 'Until',
    ],
];
