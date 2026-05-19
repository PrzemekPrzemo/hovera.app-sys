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

    'section' => [
        'pwl_required' => 'PWL-Dokumente (pflicht für Verifizierung)',
        'pwl_optional' => 'Optionale Dokumente',
        'legacy' => 'Legacy-Dokumente (zählen nicht für PWL)',
    ],

    'helper' => [
        'pwl_authorization_choice' => 'Typ 1 ODER Typ 2 wählen — je nach Transportprofil. Typ 2 (> 8h) deckt auch Typ 1 ab.',
        'pwl_vehicle_per_vehicle' => 'Pro Fahrzeug ausgestellt. Bei Flotte: zusammengeführtes PDF aller Fahrzeuge hochladen.',
        'wash_log_period' => 'Aktuell halten — Einträge älter als 12 Monate gelten als veraltet.',
    ],

    'checklist' => [
        'heading' => 'PWL-Dokumenten-Checkliste',
        'progress' => ':done von :total Dokumenten verifiziert',
        'missing_intro' => 'Fehlt:',
        'all_complete' => 'Alle Pflichtdokumente verifiziert.',
        'pwl_authorization_alternative' => 'PWL-Genehmigung (Typ 1 ODER Typ 2)',
    ],

    'admin' => [
        'verify_doc' => 'Dokument freigeben',
        'reject_doc' => 'Dokument ablehnen',
        'verify_doc_confirm' => 'Dokument freigeben? Nach Freigabe kann der Transporteur es nicht mehr löschen.',
        'rejection_reason_required' => 'Ablehnungsgrund (für Transporteur sichtbar)',
        'notify_doc_verified' => 'Dokument freigegeben',
        'notify_doc_rejected' => 'Dokument abgelehnt',
        'cannot_verify_tenant' => 'Bitte zuerst alle PWL-Pflichtdokumente verifizieren (:done/:total). Siehe Checkliste unten.',
    ],

    'expiry_notify' => [
        'subject' => 'Dokument :type läuft in :days Tagen ab',
        'greeting' => 'Hallo,',
        'intro' => 'Das Dokument „:type" für die Firma :name läuft am :date (in :days Tagen) ab.',
        'cta' => 'Bitte ein neues im Panel hochladen — sonst kann Ihr Konto am Ablaufdatum vorübergehend gesperrt werden.',
        'action' => 'Dokumente öffnen',
    ],
];
