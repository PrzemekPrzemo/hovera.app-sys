<?php

declare(strict_types=1);

return [
    'navigation' => 'KSeF (hovera)',
    'title' => 'KSeF — configuration hovera',

    'form' => [
        'section' => [
            'env' => 'Environnement',
            'cert_upload' => 'Certificat du contribuable',
            'cert_upload_description' => 'Téléversez le certificat hovera en tant qu’assujetti à la TVA (PFX avec mot de passe ou paire .crt + .key). Le certificat et le mot de passe sont chiffrés en AES-256-CBC + HMAC via Laravel Crypt.',
            'cert_current' => 'Certificat actuel',
        ],
        'label' => [
            'env' => 'Environnement KSeF',
            'context_nip' => 'NIP de hovera (contexte du contribuable)',
            'context_nip_helper' => 'NIP de l’entreprise qui émet les factures — utilisé dans l’en-tête Faktura/Podmiot1.',
            'identifier_type' => 'Identifiant dans AuthTokenRequest',
            'tab_pfx' => 'Fichier .pfx / .p12',
            'tab_pem' => 'Paire .crt + .key',
            'cert_pfx_file' => 'Fichier PFX',
            'cert_pfx_password' => 'Mot de passe PFX',
            'cert_pfx_password_helper' => 'Mot de passe utilisé lors de la création du PFX — requis pour le déchiffrement.',
            'cert_pem_crt' => 'Certificat (.crt PEM)',
            'cert_pem_key' => 'Clé privée (.key PEM)',
            'cert_pem_password' => 'Mot de passe de la clé (optionnel)',
            'cert_subject_cn' => 'CN',
            'cert_subject_nip' => 'NIP dans le cert.',
            'cert_issuer' => 'Émetteur',
            'cert_fingerprint' => 'Empreinte SHA-256',
            'cert_valid_to' => 'Valide jusqu’au',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Démo (ksef-demo.mf.gov.pl)',
            'production' => 'Production (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject (DN du cert.)',
            'fingerprint' => 'Empreinte SHA-256',
        ],
    ],

    'action' => [
        'saved' => 'Configuration enregistrée.',
        'save_button' => 'Enregistrer la configuration',
        'pfx_saved' => 'Certificat PFX enregistré.',
        'pfx_error_title' => 'Impossible de lire le PFX',
        'pem_saved' => 'Certificat PEM enregistré.',
        'pem_error_title' => 'Impossible de lire la paire PEM',
        'cant_read_file' => 'Impossible de lire le fichier téléversé.',
    ],

    'status' => [
        'pending' => 'En attente',
        'sent' => 'Envoyé',
        'accepted' => 'Accepté',
        'rejected' => 'Rejeté',
    ],
];
