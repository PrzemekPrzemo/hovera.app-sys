<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'env' => 'KSeF environment',
            'cert_upload' => 'Certificate — upload',
            'cert_upload_description' => 'Upload the certificate once. The private key + password are encrypted at the application layer (Laravel Crypt + AES-256).',
            'cert_current' => 'Currently saved certificate',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Demo (ksef-demo.mf.gov.pl)',
            'prod' => 'Production (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Certificate Subject (usually for PFX)',
            'fingerprint' => 'Fingerprint (for KSeF certificates)',
        ],
        'cert_types' => [
            'personal' => 'Qualified signature (personal)',
            'seal' => 'Electronic seal',
            'ksef' => 'KSeF certificate',
        ],
        'label' => [
            'env' => 'Environment',
            'context_nip' => 'Stable tax ID (context)',
            'context_nip_helper' => 'The tax ID used for authenticating to KSeF — same as on invoices.',
            'identifier_type' => 'Signing identifier type',
            'tab_pfx' => 'PFX / P12',
            'tab_pem' => 'PEM (.crt + .key)',
            'cert_pfx_file' => 'Certificate file (.pfx / .p12)',
            'cert_pfx_password' => 'PFX password',
            'cert_pfx_password_helper' => 'Password used ONLY for parsing — NOT stored in plain text.',
            'cert_pem_crt' => 'Certificate (.crt / .pem)',
            'cert_pem_key' => 'Private key (.key / .pem)',
            'cert_pem_password' => 'Key password (if encrypted)',
            'cert_subject_cn' => 'Subject',
            'cert_subject_nip' => 'NIP in certificate',
            'cert_issuer' => 'Issuer',
            'cert_fingerprint' => 'SHA-256 fingerprint',
            'cert_valid_to' => 'Valid until',
            'cert_type' => 'Type',
        ],
    ],

    'action' => [
        'pfx_saved' => 'PFX certificate saved.',
        'pfx_error_title' => 'PFX certificate error',
        'pem_saved' => 'PEM certificate saved.',
        'pem_error_title' => 'PEM certificate error',
        'saved' => 'KSeF settings saved',
        'cant_read_file' => 'Cannot read the uploaded certificate file.',
    ],
];
