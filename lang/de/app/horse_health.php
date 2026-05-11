<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Typ',
            'performed_at' => 'Behandlungsdatum',
            'summary' => 'Kurzbeschreibung',
            'performed_by' => 'Durchgeführt von',
            'performed_by_placeholder' => 'z. B. Assistent (falls abweichend vom Spezialisten)',
            'specialist' => 'Spezialist',
            'specialist_placeholder' => '— aus Liste wählen —',
            'next_due_at' => 'Nächste Behandlung',
            'cost' => 'Kosten',
            'details' => 'Notizen',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Datum',
            'type' => 'Typ',
            'summary' => 'Beschreibung',
            'performed_by' => 'Durchgeführt von',
            'next_due_at' => 'Nächste',
        ],
    ],
];
