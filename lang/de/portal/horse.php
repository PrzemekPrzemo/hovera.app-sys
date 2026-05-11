<?php

declare(strict_types=1);

return [
    'title' => ':horse — :tenant',
    'back' => '← Zurück zum Portal',

    'info' => [
        'breed' => 'Rasse',
        'sex' => 'Geschlecht',
        'color' => 'Farbe',
        'age' => 'Alter',
        'age_value' => ':years Jahre (:year)',
        'microchip' => 'Mikrochip',
        'passport' => 'Pass',
    ],

    'sections' => [
        'boarding' => 'Pension und Kosten',
        'feeding_plan' => 'Futterplan',
        'photos' => 'Fotogalerie',
        'activities' => 'Was wir mit Ihrem Pferd machen',
        'messages' => 'Nachrichten vom Reitstall',
        'documents' => 'Dokumente',
        'health' => 'Tierärztlicher Verlauf',
    ],

    'feeding_plan' => [
        'disclaimer' => 'Den Plan legt der Reitstall fest. Änderungen bitte per E-Mail oder im Bereich „Nachrichten" abstimmen.',
    ],

    'box' => [
        'pill' => '🏠 Box :label',
        'monthly_suffix' => '/Mon.',
        'monthly_label' => 'Pension: :rate',
    ],

    'services' => [
        'heading' => 'Abgerechnete Leistungen',
        'col_item' => 'Position',
        'col_price' => 'Preis',
        'col_frequency' => 'Frequenz',
        'col_monthly' => '~Mon.',
        'price_per_unit' => ':amount PLN / :unit',
    ],

    'cost' => [
        'monthly_label' => 'Geschätzte monatliche Kosten:',
        'monthly_disclaimer' => 'Ohne Leistungen „pro Nutzung" und einmalige — diese erscheinen nur, wenn sie abgerechnet werden.',
    ],

    'messages' => [
        'sent_flash' => '✓ Nachricht gesendet — der Reitstall wurde per E-Mail benachrichtigt.',
        'subject_placeholder' => 'Betreff (optional)',
        'body_placeholder' => 'Schreiben Sie etwas an den Reitstall…',
        'send' => 'Senden',
        'you' => 'Sie',
        'empty' => 'Keine Nachrichten — schreiben Sie die erste.',
        'attachment_fallback' => 'Anhang',
    ],

    'documents' => [
        'uploaded_flash' => '✓ Dokument hochgeladen.',
        'deleted_flash' => '✓ Dokument gelöscht.',
        'name_placeholder' => 'Dokumentname',
        'description_placeholder' => 'Beschreibung (optional)',
        'upload' => 'Dokument hochladen',
        'uploaded_by_stable' => 'Reitstall',
        'uploaded_by_you' => 'Sie',
        'valid_until' => 'gültig bis:',
        'download' => '📥 Herunterladen',
        'delete' => 'Löschen',
        'delete_confirm' => 'Dokument löschen?',
        'empty' => 'Keine Dokumente. Laden Sie das erste hoch.',
    ],

    'health' => [
        'performed_by_label' => 'Durchgeführt von: :name',
        'next_due_label' => 'Nächste Behandlung: :date',
        'overdue_pill' => 'Überfällig',
        'soon_pill' => 'Bald fällig',
        'empty' => 'Keine tierärztlichen Einträge.',
    ],
];
