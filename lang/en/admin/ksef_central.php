<?php

declare(strict_types=1);

return [
    'navigation' => 'KSeF (hovera)',
    'title' => 'KSeF — hovera config',

    'form' => [
        'section' => [
            'env' => 'Environment',
            'cert_upload' => 'Taxpayer certificate',
            'cert_upload_description' => 'Upload hovera taxpayer certificate (PFX with password, or .crt + .key pair). Cert and password are encrypted with AES-256-CBC + HMAC by Laravel Crypt.',
            'cert_current' => 'Current certificate',
        ],
        'label' => [
            'env' => 'KSeF environment',
            'context_nip' => 'hovera NIP (taxpayer context)',
            'context_nip_helper' => 'NIP of the company issuing invoices — used in Faktura/Podmiot1 header.',
            'identifier_type' => 'AuthTokenRequest identifier',
            'tab_pfx' => '.pfx / .p12 file',
            'tab_pem' => '.crt + .key pair',
            'cert_pfx_file' => 'PFX file',
            'cert_pfx_password' => 'PFX password',
            'cert_pfx_password_helper' => 'Password set when creating the PFX — needed to decrypt.',
            'cert_pem_crt' => 'Certificate (.crt PEM)',
            'cert_pem_key' => 'Private key (.key PEM)',
            'cert_pem_password' => 'Key password (optional)',
            'cert_subject_cn' => 'CN',
            'cert_subject_nip' => 'NIP in cert',
            'cert_issuer' => 'Issuer',
            'cert_fingerprint' => 'SHA-256 fingerprint',
            'cert_valid_to' => 'Valid to',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Demo (ksef-demo.mf.gov.pl)',
            'production' => 'Production (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject (DN from cert)',
            'fingerprint' => 'SHA-256 fingerprint',
        ],
    ],

    'action' => [
        'saved' => 'Configuration saved.',
        'save_button' => 'Save configuration',
        'pfx_saved' => 'PFX certificate saved.',
        'pfx_error_title' => 'Could not read PFX',
        'pem_saved' => 'PEM certificate saved.',
        'pem_error_title' => 'Could not read PEM pair',
        'cant_read_file' => 'Could not read uploaded file.',
    ],

    'status' => [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],
];
