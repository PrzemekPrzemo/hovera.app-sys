<?php

declare(strict_types=1);

return [
    'action' => [
        'create' => [
            'label' => 'Buchung hinzufügen',
            'modal_heading' => 'Neue Buchung',
            'success' => 'Buchung hinzugefügt',
            'conflict_title' => 'Konflikt',
        ],
        'edit' => [
            'label' => 'Buchung bearbeiten',
            'modal_heading' => 'Buchung bearbeiten',
            'success' => 'Buchung aktualisiert',
        ],
        'delete' => [
            'label' => 'Buchung löschen',
            'success' => 'Buchung gelöscht',
        ],
    ],

    'form' => [
        'label' => [
            'type' => 'Typ',
            'starts_at' => 'Beginn',
            'ends_at' => 'Ende',
            'horse' => 'Pferd',
            'instructor' => 'Reitlehrer',
            'arena' => 'Reitplatz',
            'client' => 'Kunde',
            'title' => 'Titel',
            'status' => 'Status',
            'notes' => 'Notizen',
        ],
    ],
];
