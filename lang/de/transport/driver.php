<?php

declare(strict_types=1);

return [
    'section' => [
        'personal' => 'Persönliche Daten',
        'contact' => 'Kontakt',
        'license' => 'Führerschein',
        'qualifications' => 'Zusätzliche Qualifikationen',
        'qualifications_description' => 'Befähigungsnachweis für Tiertransporte (EU-Richtlinie). ADR — für Gefahrgut.',
        'other' => 'Sonstiges',
    ],

    'form' => [
        'label' => [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'date_of_birth' => 'Geburtsdatum',
            'email' => 'E-Mail',
            'phone' => 'Telefon',
            'license_number' => 'Führerscheinnummer',
            'license_categories' => 'Klassen',
            'license_expires_at' => 'Gültig bis',
            'has_animal_transport_cert' => 'Tiertransport-Zertifikat',
            'animal_transport_cert_expires_at' => 'Gültig bis',
            'has_adr' => 'ADR',
            'adr_expires_at' => 'Gültig bis',
            'hire_date' => 'Einstellungsdatum',
            'is_active' => 'Aktiv',
            'sort_order' => 'Reihenfolge',
            'notes' => 'Notizen',
        ],
        'helper' => [
            'email' => 'Adresse für Dispositionsbenachrichtigungen.',
        ],
    ],

    'table' => [
        'column' => [
            'full_name' => 'Fahrer',
            'phone' => 'Telefon',
            'email' => 'E-Mail',
            'license_expires_at' => 'Führersch. bis',
            'has_animal_transport_cert' => 'Tiertransp.',
            'is_active' => 'Aktiv',
        ],
    ],

    'filter' => [
        'license_expiring_soon' => 'Führerschein läuft in 30 Tagen ab',
    ],
];
