<?php

declare(strict_types=1);

return [
    'uploaded_by' => [
        'stable' => 'Reitstall',
        'client' => 'Kunde',
    ],

    'form' => [
        'label' => [
            'name' => 'Dokumentname',
            'name_placeholder' => 'z. B. Pass von Bucephalus',
            'kind' => 'Kategorie',
            'description' => 'Beschreibung (optional)',
            'file' => 'Datei (max. 25 MB)',
            'valid_from' => 'Gültig ab (optional)',
            'valid_until' => 'Gültig bis (optional)',
        ],
    ],

    'table' => [
        'column' => [
            'kind' => 'Kategorie',
            'name' => 'Name',
            'original_name' => 'Datei',
            'size' => 'Größe',
            'uploaded_by' => 'Hochgeladen von',
            'valid_until' => 'Gültig bis',
            'created_at' => 'Hochgeladen',
        ],
        'filter' => [
            'expiring_soon' => 'Läuft in 30 Tagen ab',
        ],
    ],

    'action' => [
        'create' => [
            'label' => 'Dokument hochladen',
            'no_file' => 'Keine Datei.',
            'failed' => 'Upload fehlgeschlagen',
        ],
        'download' => [
            'label' => 'Herunterladen',
        ],
    ],
];
