<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'data' => 'Reitlehrer-Daten',
        ],
        'label' => [
            'name' => 'Vor- und Nachname',
            'phone' => 'Telefon',
            'hourly_rate' => 'Stundensatz',
            'color' => 'Farbe im Kalender',
            'is_active' => 'Aktiv',
            'notes' => 'Notizen',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Vor- und Nachname',
            'phone' => 'Telefon',
            'hourly_rate' => 'Satz',
            'color' => 'Farbe',
            'is_active' => 'Aktiv',
        ],
        'filter' => [
            'status' => 'Status',
        ],
    ],

    'actions' => [
        'ics_url' => 'Kalender .ics',
    ],
    'ics_modal' => [
        'heading' => 'Kalender des Reitlehrers :name',
        'description' => 'Kopieren Sie die URL und fügen Sie sie in Google Calendar / Outlook / Apple Calendar als „Kalender per URL hinzufügen" ein. Reitstunden erscheinen automatisch und werden alle paar Stunden synchronisiert.',
        'url_label' => 'Feed-URL (Abonnement)',
        'howto' => 'Google Calendar → „Weitere Kalender" → „+ → Per URL" → URL einfügen. Outlook → „Kalender hinzufügen → Aus dem Internet abonnieren". Apple → File → New Calendar Subscription.',
        'token_ensured' => 'URL bereit',
        'close' => 'Schließen',
    ],
];
