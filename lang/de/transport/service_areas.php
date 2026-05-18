<?php

declare(strict_types=1);

return [
    'navigation' => 'Servicegebiet',
    'title' => 'Servicewojewodschaften',

    'section' => [
        'heading' => 'Wojewodschaften auswählen',
        'description' => 'Markieren Sie die, in denen Sie tätig sind. Im Broadcast-Modus erhalten Sie Anfragen aus diesen und benachbarten Wojewodschaften (Adjazenzkarte).',
    ],

    'form' => [
        'label' => [
            'voivodeships' => 'Wojewodschaften',
        ],
    ],

    'action' => [
        'save' => 'Auswahl speichern',
    ],

    'notify' => [
        'saved' => 'Servicegebiet aktualisiert',
        'saved_body' => 'Markiert: :direct Wojewodschaften, gesamte Abdeckung mit Adjazenz: :effective.',
    ],
];
