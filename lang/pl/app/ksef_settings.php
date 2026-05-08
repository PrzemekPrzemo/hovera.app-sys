<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'env' => 'Środowisko KSeF',
            'cert_upload' => 'Certyfikat — upload',
            'cert_upload_description' => 'Jednorazowo wgrywasz certyfikat. Klucz prywatny + hasło są zaszyfrowane na poziomie aplikacji (Laravel Crypt + AES-256).',
            'cert_current' => 'Aktualnie zapisany certyfikat',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Demo (ksef-demo.mf.gov.pl)',
            'prod' => 'Produkcyjne (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject certyfikatu (zwykle dla PFX)',
            'fingerprint' => 'Fingerprint (dla certyfikatów KSeF)',
        ],
        'cert_types' => [
            'personal' => 'Podpis kwalifikowany (osobowy)',
            'seal' => 'Pieczęć elektroniczna',
            'ksef' => 'Certyfikat KSeF',
        ],
        'label' => [
            'env' => 'Środowisko',
            'context_nip' => 'NIP stajni (kontekst)',
            'context_nip_helper' => 'NIP używany przy uwierzytelnianiu w KSeF — ten sam co na fakturach.',
            'identifier_type' => 'Typ identyfikatora podpisującego',
            'tab_pfx' => 'PFX / P12',
            'tab_pem' => 'PEM (.crt + .key)',
            'cert_pfx_file' => 'Plik certyfikatu (.pfx / .p12)',
            'cert_pfx_password' => 'Hasło PFX',
            'cert_pfx_password_helper' => 'Hasło używane TYLKO przy parsowaniu — NIE jest zapisywane w plain text.',
            'cert_pem_crt' => 'Certyfikat (.crt / .pem)',
            'cert_pem_key' => 'Klucz prywatny (.key / .pem)',
            'cert_pem_password' => 'Hasło klucza (jeśli zaszyfrowany)',
            'cert_subject_cn' => 'Podmiot',
            'cert_subject_nip' => 'NIP w certyfikacie',
            'cert_issuer' => 'Wystawca',
            'cert_fingerprint' => 'Fingerprint SHA-256',
            'cert_valid_to' => 'Ważny do',
            'cert_type' => 'Typ',
        ],
    ],

    'action' => [
        'pfx_saved' => 'Certyfikat PFX zapisany.',
        'pfx_error_title' => 'Błąd certyfikatu PFX',
        'pem_saved' => 'Certyfikat PEM zapisany.',
        'pem_error_title' => 'Błąd certyfikatu PEM',
        'saved' => 'Zapisano ustawienia KSeF',
        'cant_read_file' => 'Nie można odczytać przesłanego pliku certyfikatu.',
    ],
];
