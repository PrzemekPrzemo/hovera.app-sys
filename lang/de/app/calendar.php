<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'time_type' => 'Zeit und Typ',
            'resources' => 'Ressourcen',
            'details' => 'Details',
            'participants' => 'Teilnehmer der Gruppenstunde',
            'participants_description' => 'Jeder Teilnehmer = Kunde + optional Pferd. Nach der Reitstunde markieren Sie die Anwesenheit pro Teilnehmer.',
        ],
        'label' => [
            'type' => 'Typ',
            'starts_at' => 'Beginn',
            'ends_at' => 'Ende',
            'horse' => 'Pferd',
            'instructor' => 'Reitlehrer',
            'arena' => 'Reitplatz',
            'client' => 'Kunde',
            'title' => 'Titel (für Veranstaltungen / Sperren)',
            'status' => 'Status',
            'price' => 'Preis',
            'notes' => 'Notizen',
            'participants' => 'Teilnehmer',
            'participant_client' => 'Kunde',
            'participant_horse' => 'Pferd (optional)',
            'participant_horse_placeholder' => '— reitet auf eigenem Pferd / wird später zugewiesen —',
            'participant_attendance' => 'Anwesenheit',
            'participant_notes' => 'Notizen (z. B. „erste Reitstunde")',
        ],
    ],

    'attendance' => [
        'expected' => 'Erwartet',
        'present' => 'Anwesend',
        'absent' => 'Abwesend',
        'late' => 'Verspätet',
    ],

    'actions' => [
        'add_participant' => '+ Teilnehmer hinzufügen',
    ],

    'table' => [
        'column' => [
            'starts_at' => 'Beginn',
            'ends_at' => 'Ende',
            'type' => 'Typ',
            'horse' => 'Pferd',
            'instructor' => 'Reitlehrer',
            'arena' => 'Reitplatz',
            'client' => 'Kunde',
            'status' => 'Status',
        ],
        'participant_count' => '{0} keine Teilnehmer|{1} 👥 :count Teilnehmer|[2,*] 👥 :count Teilnehmer',
        'filter' => [
            'horse' => 'Pferd',
            'instructor' => 'Reitlehrer',
            'upcoming' => 'Nur anstehende',
        ],
    ],
];
