<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'KSeF (polnisches e-Invoicing)',
        'description' => 'KSeF-Integration für Transportrechnungen — ausgestellt durch Sie.',
        'disclaimer' => 'Sie erhalten Ihr KSeF-Token in Ihrem eigenen KSeF-Konto (mf.gov.pl). '
            .'Hovera leitet Ihre Rechnungen nur weiter — es ist IHR Token, IHRE Steuernummer, '
            .'IHRE Verantwortung für die buchhalterische Compliance. Hovera ist weder Vertragspartei '
            .'Ihrer Transportverträge noch Aussteller Ihrer Rechnungen (siehe docs/TRANSPORT.md §12).',
        'invoice_title' => 'KSeF — Versandstatus',
        'invoice_description' => 'Informationen zur Übermittlung an KSeF (falls aktiviert).',
    ],

    'form' => [
        'label' => [
            'nip' => 'Steuernummer des Ausstellers (Ihre)',
            'environment' => 'KSeF-Umgebung',
            'token' => 'KSeF-Autorisierungstoken',
            'enabled' => 'KSeF-Integration aktivieren',
            'invoice_status' => 'KSeF-Status',
            'reference_number' => 'KSeF-Referenznummer',
            'submitted_at' => 'Übermittelt am',
        ],
        'helper' => [
            'nip' => '10-stellige polnische Steuernummer.',
            'token_empty' => 'Fügen Sie das im MF-Panel generierte Token ein. Wir speichern es verschlüsselt.',
            'token_set' => 'Token gespeichert. Neuen Wert eingeben zum Ersetzen.',
            'enabled' => 'Nach Aktivierung erscheint die Aktion „An KSeF senden".',
        ],
        'option' => [
            'environment' => [
                'test' => 'Test (ksef-test.mf.gov.pl)',
                'demo' => 'Demo (ksef-demo.mf.gov.pl)',
                'production' => 'Produktion (ksef.mf.gov.pl)',
            ],
        ],
    ],

    'action' => [
        'submit' => 'An KSeF senden',
        'submit_confirm' => 'Diese Rechnung an KSeF übermitteln? Kann nicht rückgängig gemacht werden.',
        'submit_bulk' => 'Ausgewählte an KSeF senden',
        'submit_bulk_confirm' => 'Ausgewählte Rechnungen (max. 50) an KSeF senden? Irreversibel.',
        'refresh' => 'KSeF-Status aktualisieren',
        'test_connection' => 'KSeF-Verbindung testen',
    ],

    'notify' => [
        'submitted' => 'Rechnung an KSeF übermittelt.',
        'submit_failed' => 'KSeF-Übermittlung fehlgeschlagen.',
        'status_refreshed' => 'KSeF-Status aktualisiert.',
        'not_configured' => 'KSeF ist nicht konfiguriert.',
        'unknown_error' => 'Unbekannter KSeF-Fehler.',
        'test_ok' => 'KSeF-Verbindung funktioniert.',
        'test_failed' => 'KSeF-Verbindung fehlgeschlagen.',
        'bulk_done' => 'Massenversand abgeschlossen.',
        'bulk_done_body' => 'Erfolg: :ok. Fehler: :fail.',
    ],

    'status' => [
        'not_submitted' => 'Nicht übermittelt',
        'submitted' => 'Übermittelt',
        'accepted' => 'Akzeptiert',
        'rejected' => 'Abgelehnt',
        'error' => 'Fehler',
    ],

    'table' => [
        'column' => [
            'status' => 'KSeF',
        ],
    ],
];
