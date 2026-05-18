<?php

declare(strict_types=1);

return [
    'navigation' => 'Kontoverifizierung',
    'title' => 'Verifizierungsdokumente',

    'status' => [
        'heading' => 'Verifizierungsstatus',
        'pending_body' => 'Um Ihr Konto zu aktivieren, laden Sie :count fehlende Dokumente hoch. Ohne Verifizierung können Sie keine Angebote oder Rechnungen versenden.',
        'under_review_body' => 'Alle Pflichtdokumente hochgeladen — Prüfung durch Hovera-Team läuft (1–2 Werktage).',
        'verified_body' => 'Konto aktiv. Angebote, Rechnungen und Marktplatzanfragen sind freigeschaltet.',
        'rejected_body' => 'Konto abgelehnt. Prüfen Sie die Anmerkungen und laden Sie korrigierte Versionen hoch.',
        'missing_badge' => ':count fehlt',
    ],

    'label' => [
        'required' => 'Pflicht',
        'optional' => 'optional',
        'uploaded_at' => 'Hochgeladen',
        'expires_at' => 'Gültig bis',
        'issued_at' => 'Ausstellungsdatum',
        'expired' => 'ABGELAUFEN',
        'expiring_soon' => 'läuft bald ab',
        'rejection_reason' => 'Ablehnungsgrund',
    ],

    'action' => [
        'upload' => 'Hochladen',
        'delete' => 'Löschen',
    ],

    'confirm' => [
        'delete' => 'Dieses Dokument löschen? Diese Aktion kann nicht rückgängig gemacht werden.',
    ],

    'notify' => [
        'uploaded' => 'Dokument hochgeladen',
        'deleted' => 'Dokument gelöscht',
        'error' => 'Fehler',
    ],

    'error' => [
        'no_file' => 'Wählen Sie eine Datei aus, bevor Sie "Hochladen" klicken.',
        'bad_mime' => 'Dateiformat nicht erlaubt. Erlaubt: :allowed.',
        'too_large' => 'Datei zu groß. Maximal :limit.',
    ],

    'footer' => [
        'allowed_formats' => 'Akzeptiert: PDF, JPG, PNG. Maximal 10 MB pro Datei. Alle Dateien in verschlüsseltem EU-Speicher.',
    ],
];
