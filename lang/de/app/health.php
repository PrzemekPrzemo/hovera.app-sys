<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'entry' => 'Eintrag',
            'details' => 'Details',
        ],
        'label' => [
            'horse' => 'Pferd',
            'template' => 'Behandlungsvorlage',
            'template_placeholder' => '— optional Vorlage wählen —',
            'type' => 'Typ',
            'performed_at' => 'Behandlungsdatum',
            'performed_by' => 'Durchgeführt von (Tierarzt / Hufschmied / Firma)',
            'performed_by_placeholder' => 'z. B. Assistent des Hufschmieds (falls abweichend)',
            'specialist' => 'Spezialist',
            'specialist_placeholder' => '— aus Spezialistenliste wählen —',
            'summary' => 'Kurzbeschreibung',
            'summary_placeholder' => 'Impfung Tetanus + Grippe',
            'next_due_at' => 'Nächste Behandlung',
            'cost' => 'Kosten',
            'details' => 'Notizen / Medikamente / Empfehlungen',
        ],
        'helper' => [
            'template' => 'Die Auswahl einer Vorlage füllt Typ, Beschreibung und vorgeschlagenen Folgetermin aus.',
            'next_due_at' => 'Dadurch erscheint ein Alarm auf dem Dashboard.',
            'specialist' => 'Liste gefiltert nach Eintragstyp — Hufschmiede für „Hufschmied", Tierärzte für übrige Typen. Liste konfigurieren unter Reitstall → Spezialisten.',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Datum',
            'horse' => 'Pferd',
            'type' => 'Typ',
            'summary' => 'Beschreibung',
            'performed_by' => 'Durchgeführt von',
            'next_due_at' => 'Nächste',
            'cost' => 'Kosten',
        ],
        'filter' => [
            'horse' => 'Pferd',
            'overdue' => 'Überfällig (next due in past)',
            'due_30' => 'Nächste in 30 Tagen',
        ],
    ],
];
