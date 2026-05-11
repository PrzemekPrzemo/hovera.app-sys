<?php

declare(strict_types=1);

return [
    'navigation' => 'KSeF (hovera)',
    'title' => 'KSeF — hovera-Konfiguration',

    'form' => [
        'section' => [
            'env' => 'Umgebung',
            'cert_upload' => 'Steuerpflichtigen-Zertifikat',
            'cert_upload_description' => 'Laden Sie das Zertifikat von hovera als MwSt.-Pflichtiger hoch (PFX mit Passwort oder Paar aus .crt + .key). Zertifikat und Passwort werden per AES-256-CBC + HMAC durch Laravel Crypt verschlüsselt.',
            'cert_current' => 'Aktuelles Zertifikat',
        ],
        'label' => [
            'env' => 'KSeF-Umgebung',
            'context_nip' => 'NIP von hovera (Steuerpflichtigen-Kontext)',
            'context_nip_helper' => 'NIP der Firma, die Rechnungen ausstellt — wird im Header Faktura/Podmiot1 verwendet.',
            'identifier_type' => 'Identifikation im AuthTokenRequest',
            'tab_pfx' => 'Datei .pfx / .p12',
            'tab_pem' => 'Paar .crt + .key',
            'cert_pfx_file' => 'PFX-Datei',
            'cert_pfx_password' => 'PFX-Passwort',
            'cert_pfx_password_helper' => 'Passwort, mit dem das PFX erstellt wurde — zur Entschlüsselung erforderlich.',
            'cert_pem_crt' => 'Zertifikat (.crt PEM)',
            'cert_pem_key' => 'Privater Schlüssel (.key PEM)',
            'cert_pem_password' => 'Schlüsselpasswort (optional)',
            'cert_subject_cn' => 'CN',
            'cert_subject_nip' => 'NIP im Zertifikat',
            'cert_issuer' => 'Aussteller',
            'cert_fingerprint' => 'Fingerprint SHA-256',
            'cert_valid_to' => 'Gültig bis',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Demo (ksef-demo.mf.gov.pl)',
            'production' => 'Produktion (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject (DN aus Zert.)',
            'fingerprint' => 'Fingerprint SHA-256',
        ],
    ],

    'action' => [
        'saved' => 'Konfiguration gespeichert.',
        'save_button' => 'Konfiguration speichern',
        'pfx_saved' => 'PFX-Zertifikat gespeichert.',
        'pfx_error_title' => 'PFX kann nicht gelesen werden',
        'pem_saved' => 'PEM-Zertifikat gespeichert.',
        'pem_error_title' => 'PEM-Paar kann nicht gelesen werden',
        'cant_read_file' => 'Hochgeladene Datei kann nicht gelesen werden.',
    ],

    'status' => [
        'pending' => 'Ausstehend',
        'sent' => 'Gesendet',
        'accepted' => 'Akzeptiert',
        'rejected' => 'Abgelehnt',
    ],
];
