<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Typ',
            'performed_at' => 'Wann',
            'performed_by' => 'Durchgeführt von (Name des Stallmitarbeiters)',
            'performed_by_placeholder' => 'z. B. Tomek (falls abweichend vom Spezialisten)',
            'specialist' => 'Spezialist (Hufschmied / Tierarzt)',
            'specialist_placeholder' => '— falls von einem Spezialisten durchgeführt, aus der Liste wählen —',
            'cost' => 'Zusatzkosten (optional)',
            'summary' => 'Kurzbeschreibung',
            'summary_placeholder' => 'z. B. „Auslauf 9:00–12:00, östliche Koppel"',
            'details' => 'Notizen',
        ],
        'helper' => [
            'cost' => 'Nur eingeben, wenn die Aktivität Kosten außerhalb der Pauschale verursacht hat (z. B. zusätzliches Heu, Transport).',
            'specialist' => 'Liste aller aktiven Spezialisten (Hufschmiede + Tierärzte). Konfigurieren unter Reitstall → Spezialisten.',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Datum',
            'type' => 'Typ',
            'summary' => 'Beschreibung',
            'performed_by' => 'Durchgeführt von',
            'cost' => 'Kosten',
        ],
    ],
];
