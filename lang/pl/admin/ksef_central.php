<?php

declare(strict_types=1);

return [
    'navigation' => 'KSeF (hovera)',
    'title' => 'KSeF — konfiguracja hovery',

    'form' => [
        'section' => [
            'env' => 'Środowisko',
            'cert_upload' => 'Certyfikat podatnika',
            'cert_upload_description' => 'Wgraj certyfikat hovery jako podatnika VAT (PFX z hasłem albo para .crt + .key). Cert wraz z hasłem szyfrowany jest AES-256-CBC + HMAC przez Laravel Crypt.',
            'cert_current' => 'Aktualny certyfikat',
        ],
        'label' => [
            'env' => 'Środowisko KSeF',
            'context_nip' => 'NIP hovery (kontekst podatnika)',
            'context_nip_helper' => 'NIP firmy która wystawia faktury — używany w nagłówku Faktura/Podmiot1.',
            'identifier_type' => 'Identyfikacja w AuthTokenRequest',
            'tab_pfx' => 'Plik .pfx / .p12',
            'tab_pem' => 'Para .crt + .key',
            'cert_pfx_file' => 'Plik PFX',
            'cert_pfx_password' => 'Hasło PFX',
            'cert_pfx_password_helper' => 'Hasło z którego utworzono PFX — wymagane do odszyfrowania.',
            'cert_pem_crt' => 'Certyfikat (.crt PEM)',
            'cert_pem_key' => 'Klucz prywatny (.key PEM)',
            'cert_pem_password' => 'Hasło klucza (opcjonalne)',
            'cert_subject_cn' => 'CN',
            'cert_subject_nip' => 'NIP w cert',
            'cert_issuer' => 'Wystawca',
            'cert_fingerprint' => 'Fingerprint SHA-256',
            'cert_valid_to' => 'Ważny do',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Demo (ksef-demo.mf.gov.pl)',
            'production' => 'Produkcja (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject (DN z cert)',
            'fingerprint' => 'Fingerprint SHA-256',
        ],
    ],

    'action' => [
        'saved' => 'Konfiguracja zapisana.',
        'save_button' => 'Zapisz konfigurację',
        'pfx_saved' => 'Certyfikat PFX zapisany.',
        'pfx_error_title' => 'Nie można odczytać PFX',
        'pem_saved' => 'Certyfikat PEM zapisany.',
        'pem_error_title' => 'Nie można odczytać pary PEM',
        'cant_read_file' => 'Nie można odczytać wgranego pliku.',
    ],

    'status' => [
        'pending' => 'Oczekuje',
        'sent' => 'Wysłano',
        'accepted' => 'Zaakceptowano',
        'rejected' => 'Odrzucono',
    ],
];
