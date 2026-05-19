<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identifikation',
            'location' => 'Standort',
            'subscription' => 'Abonnement',
            'branding' => 'Branding',
            'branding_description' => 'Wird auf der öffentlichen Seite /s/{slug} und in E-Mails verwendet.',
            'public_profile' => 'Öffentliches Profil',
            'public_profile_description' => 'Auf der öffentlichen Reitstall-Seite /s/{slug} angezeigte Daten.',
            'database' => 'Datenbank',
        ],
        'label' => [
            'type' => 'Mandanten-Typ',
            'tax_id' => 'USt-IdNr. / VAT-ID',
            'plan' => 'Tarif',
            'primary_color' => 'Hauptfarbe',
            'logo_url' => 'Logo-URL',
            'public_description' => 'Beschreibung des Reitstalls',
            'public_email' => 'Kontakt-E-Mail (öffentlich)',
            'public_phone' => 'Kontakttelefon',
            'public_address' => 'Adresse',
            'public_website' => 'Website',
        ],
        'option' => [
            'type' => [
                'stable' => 'Reitstall',
                'transporter' => 'Transportunternehmen',
            ],
        ],
        'helper' => [
            'slug' => 'Unveränderlich. Wird in URLs und Datenbanknamen verwendet.',
            'type' => 'Bestimmt das Panel nach dem Login (Reitstall → /app, Transport → /transport) und welche Tarife verfügbar sind. Nach Erstellung unveränderlich.',
            'plan' => 'Tarifliste gefiltert nach gewähltem Mandantentyp.',
        ],
    ],

    'notify' => [
        'created_stable' => 'Reitstall erstellt',
        'created_transporter' => 'Transportunternehmen erstellt',
        'created_body' => 'Datenbank :db wurde initialisiert.',
        'create_failed' => 'Mandantenerstellung fehlgeschlagen',
    ],

    'table' => [
        'column' => [
            'type' => 'Typ',
            'country' => 'Land',
            'plan' => 'Tarif',
            'db_name' => 'Datenbank',
            'created_at' => 'Erstellt',
        ],
        'filter' => [
            'type' => 'Mandantentyp',
        ],
    ],

    'action' => [
        'suspend' => [
            'label' => 'Sperren',
            'notification_title' => 'Reitstall gesperrt',
        ],
        'reactivate' => [
            'label' => 'Erneut aktivieren',
            'notification_title' => 'Reitstall wieder aktiv',
        ],
        'soft_delete' => [
            'label' => 'Soft Delete',
        ],
        'login_as_owner' => [
            'label' => 'Als Reitstall anmelden',
            'reason_label' => 'Grund der Impersonation (DSGVO-Audit)',
            'reason_helper' => 'Erforderlich. Die Sitzung wird in impersonation_sessions + audit_log_master eingetragen.',
            'submit' => 'Impersonation starten',
            'no_user_title' => 'Kein aktiver Benutzer für diesen Reitstall',
            'no_user_body' => 'Fügen Sie zuerst ein Teammitglied hinzu oder laden Sie einen Owner ein.',
        ],
        'seed_demo' => [
            'label' => 'Demodaten einspielen',
            'modal_heading' => 'Demodaten in :name einspielen?',
            'modal_description' => 'Fügt 14 Pferde, 6 Kunden, 12 Boxen, Kalender, Rechnungen und den restlichen Demo-Datensatz hinzu. Läuft auf der Tenant-Datenbank.',
            'fresh_label' => 'Vorhandene Daten löschen (DROP all tables)',
            'fresh_helper' => 'ACHTUNG: Löscht alle aktuellen Reitstall-Daten vor dem Seed.',
            'success_title' => 'Demodaten eingespielt',
            'success_body' => 'Der Reitstall :name verfügt nun über den vollständigen Demo-Datensatz.',
            'failure_title' => 'Demo konnte nicht eingespielt werden',
        ],
        'destroy' => [
            'label' => 'Datenbank löschen',
            'modal_heading' => 'Reitstall endgültig löschen',
            'modal_description' => 'Diese Aktion kann NICHT rückgängig gemacht werden. Die Datenbank :db und das MySQL-Konto :user werden physisch gelöscht.',
            'confirm_slug_label' => 'Geben Sie den Reitstall-Slug zur Bestätigung ein',
            'slug_mismatch' => 'Slug stimmt nicht überein.',
            'success_title' => 'Reitstall endgültig gelöscht',
        ],
    ],
];
