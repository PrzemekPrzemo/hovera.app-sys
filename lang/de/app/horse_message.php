<?php

declare(strict_types=1);

return [
    'directions' => [
        'from_stable' => 'Reitstall → Kunde',
        'from_client' => 'Kunde → Reitstall',
    ],

    'form' => [
        'label' => [
            'subject' => 'Betreff (optional)',
            'body' => 'Inhalt',
            'attachments' => 'Anhänge (max. 5, je bis 10 MB)',
        ],
    ],

    'table' => [
        'column' => [
            'sent_at' => 'Gesendet',
            'direction' => 'Richtung',
            'subject' => 'Betreff',
            'body' => 'Auszug',
            'attachments_short' => 'Anh.',
            'read_short' => 'Gel.',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'An Besitzer schreiben',
            'failed' => 'Versand fehlgeschlagen',
            'sent' => 'Nachricht gesendet',
        ],
        'mark_read' => [
            'label' => 'Als gelesen markieren',
            'success' => 'Als gelesen markiert',
        ],
    ],
];
