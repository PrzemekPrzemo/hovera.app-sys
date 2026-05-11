<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'env' => 'KSeF-Umgebung',
            'cert_upload' => 'Zertifikat — Upload',
            'cert_upload_description' => 'Sie laden das Zertifikat einmalig hoch. Der private Schlüssel + Passwort werden auf Anwendungsebene verschlüsselt (Laravel Crypt + AES-256).',
            'cert_current' => 'Aktuell gespeichertes Zertifikat',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Demo (ksef-demo.mf.gov.pl)',
            'prod' => 'Produktion (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject des Zertifikats (üblich bei PFX)',
            'fingerprint' => 'Fingerprint (für KSeF-Zertifikate)',
        ],
        'cert_types' => [
            'personal' => 'Qualifizierte Signatur (persönlich)',
            'seal' => 'Elektronisches Siegel',
            'ksef' => 'KSeF-Zertifikat',
        ],
        'label' => [
            'env' => 'Umgebung',
            'context_nip' => 'NIP des Reitstalls (Kontext)',
            'context_nip_helper' => 'Die bei der KSeF-Authentifizierung verwendete NIP — identisch mit der auf den Rechnungen.',
            'identifier_type' => 'Typ des Signatur-Identifikators',
            'tab_pfx' => 'PFX / P12',
            'tab_pem' => 'PEM (.crt + .key)',
            'cert_pfx_file' => 'Zertifikatsdatei (.pfx / .p12)',
            'cert_pfx_password' => 'PFX-Passwort',
            'cert_pfx_password_helper' => 'Passwort wird NUR zum Parsen verwendet — es wird NICHT im Klartext gespeichert.',
            'cert_pem_crt' => 'Zertifikat (.crt / .pem)',
            'cert_pem_key' => 'Privater Schlüssel (.key / .pem)',
            'cert_pem_password' => 'Schlüsselpasswort (falls verschlüsselt)',
            'cert_subject_cn' => 'Subjekt',
            'cert_subject_nip' => 'NIP im Zertifikat',
            'cert_issuer' => 'Aussteller',
            'cert_fingerprint' => 'Fingerprint SHA-256',
            'cert_valid_to' => 'Gültig bis',
            'cert_type' => 'Typ',
        ],
    ],

    'action' => [
        'pfx_saved' => 'PFX-Zertifikat gespeichert.',
        'pfx_error_title' => 'PFX-Zertifikat-Fehler',
        'pem_saved' => 'PEM-Zertifikat gespeichert.',
        'pem_error_title' => 'PEM-Zertifikat-Fehler',
        'saved' => 'KSeF-Einstellungen gespeichert',
        'cant_read_file' => 'Die hochgeladene Zertifikatsdatei kann nicht gelesen werden.',
    ],
];
