<?php

declare(strict_types=1);

return [
    'directions' => [
        'from_stable' => 'Stajnia → Klient',
        'from_client' => 'Klient → Stajnia',
    ],

    'form' => [
        'label' => [
            'subject' => 'Temat (opcjonalnie)',
            'body' => 'Treść',
            'attachments' => 'Załączniki (max 5, do 10 MB każdy)',
        ],
    ],

    'table' => [
        'column' => [
            'sent_at' => 'Wysłana',
            'direction' => 'Kierunek',
            'subject' => 'Temat',
            'body' => 'Fragment',
            'attachments_short' => 'Zał.',
            'read_short' => 'Odcz.',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Napisz do właściciela',
            'failed' => 'Nie udało się wysłać',
            'sent' => 'Wiadomość wysłana',
        ],
        'mark_read' => [
            'label' => 'Oznacz jako przeczytaną',
            'success' => 'Oznaczono jako przeczytaną',
        ],
    ],
];
