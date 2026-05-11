<?php

declare(strict_types=1);

return [
    'navigation' => 'Datenimport',
    'title' => 'Datenimport aus Excel/CSV',
    'intro' => 'Importieren Sie eine Liste von Kunden oder Pferden aus einer Excel-Tabelle / CSV-Datei. Unterstützte Quellen: Export aus Nasza Stajnia, Horstable oder jede beliebige Datei mit Spaltenüberschriften in der ersten Zeile.',

    'template' => [
        'clients' => 'Vorlage herunterladen — Kunden',
        'horses' => 'Vorlage herunterladen — Pferde',
    ],

    'steps' => [
        'entity' => [
            'title' => 'Was importieren Sie?',
            'description' => 'Wählen Sie den Datentyp für den Import.',
        ],
        'file' => [
            'title' => 'Datei hochladen',
            'description' => 'Akzeptiert werden .xlsx, .xls, .csv (max. 10 MB).',
        ],
        'mapping' => [
            'title' => 'Spaltenzuordnung',
            'description' => 'Ordnen Sie die Spalten der Datei den Feldern in hovera zu.',
        ],
        'preview' => [
            'title' => 'Vorschau und Import',
            'description' => 'Überprüfen Sie die ersten 5 Zeilen, bevor Sie den Import starten.',
        ],
    ],

    'fields' => [
        'entity' => 'Datentyp',
        'file' => 'Datendatei',
        'clients' => [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'street' => 'Straße',
            'postal_code' => 'PLZ',
            'city' => 'Stadt',
            'tax_id' => 'USt-IdNr.',
            'notes' => 'Notizen',
        ],
        'horses' => [
            'name' => 'Pferdename',
            'breed' => 'Rasse',
            'sex' => 'Geschlecht',
            'color' => 'Farbe',
            'birth_date' => 'Geburtsdatum',
            'microchip' => 'Mikrochip',
            'passport_number' => 'Passnummer',
            'client_email' => 'E-Mail des Besitzers',
            'notes' => 'Notizen',
        ],
    ],

    'entity' => [
        'clients' => 'Kunden',
        'clients_hint' => 'Pferdebesitzer / Einsteller.',
        'horses' => 'Pferde',
        'horses_hint' => 'Pensions- und Schulpferde.',
    ],

    'skip' => 'überspringen',
    'upload_first' => 'Laden Sie im vorherigen Schritt eine Datei hoch, um die Spalten zuordnen zu können.',
    'parse_pending' => 'Warte auf Datei...',
    'parse_summary' => ':rows Datenzeilen in :cols Spalten erkannt.',
    'parse_failed' => 'Datei konnte nicht gelesen werden',
    'no_file' => 'Keine Datei — gehen Sie zu Schritt 2 zurück.',

    'preview' => [
        'empty' => 'Keine Daten zur Anzeige.',
        'status' => 'Status',
        'ok' => 'OK',
        'note' => 'Oben sehen Sie die ersten 5 Zeilen. Die übrigen werden während des Imports validiert — fehlerhafte Zeilen werden übersprungen und in der Zusammenfassung aufgelistet.',
    ],

    'actions' => [
        'import' => 'Importieren',
    ],

    'flash' => [
        'success' => ':count Datensätze importiert.',
        'failed' => ':count Zeilen mit Fehlern übersprungen.',
    ],

    'result' => [
        'heading' => 'Import-Ergebnis',
        'summary' => 'Importiert: :ok · Übersprungen: :failed.',
    ],
];
