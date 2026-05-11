<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'pass' => 'Reitkarte',
        ],
        'label' => [
            'client' => 'Kunde',
            'name' => 'Name',
            'name_placeholder' => '8er-Reitkarte',
            'total_uses' => 'Anzahl Reitstunden',
            'remaining_uses' => 'Verbleibend',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'price' => 'Reitkarten-Preis',
            'cancellation_policy_hours' => 'Stornierungs-Policy (h)',
            'cancellation_policy_placeholder' => 'Standard aus Reitstall-Einstellungen verwenden',
            'status' => 'Status',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'remaining_uses' => 'Wird vom System automatisch aktualisiert; manuelle Änderung nur in Ausnahmefällen.',
            'cancellation_policy_hours' => 'Stornierung X Stunden vor dem Termin = kostenfrei (Reitkarte wird zurückerstattet).',
        ],
    ],

    'table' => [
        'column' => [
            'client' => 'Kunde',
            'name' => 'Reitkarte',
            'remaining_uses' => 'Verbleibend',
            'status' => 'Status',
            'valid_until' => 'Gültig bis',
            'price' => 'Preis',
            'cancellation_policy' => 'Stornierung',
            'cancellation_policy_default' => 'gemäß Reitstall-Einstellungen',
            'created_at' => 'Ausgestellt',
        ],
        'filter' => [
            'client' => 'Kunde',
        ],
    ],
];
