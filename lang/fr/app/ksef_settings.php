<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'env' => 'Environnement KSeF',
            'cert_upload' => 'Certificat — téléversement',
            'cert_upload_description' => 'Téléversez le certificat une seule fois. La clé privée et le mot de passe sont chiffrés au niveau de l’application (Laravel Crypt + AES-256).',
            'cert_current' => 'Certificat actuellement enregistré',
        ],
        'env_options' => [
            'test' => 'Test (ksef-test.mf.gov.pl)',
            'demo' => 'Démo (ksef-demo.mf.gov.pl)',
            'prod' => 'Production (ksef.mf.gov.pl)',
        ],
        'identifier_options' => [
            'subject' => 'Subject du certificat (habituellement pour PFX)',
            'fingerprint' => 'Empreinte (pour les certificats KSeF)',
        ],
        'cert_types' => [
            'personal' => 'Signature qualifiée (personnelle)',
            'seal' => 'Sceau électronique',
            'ksef' => 'Certificat KSeF',
        ],
        'label' => [
            'env' => 'Environnement',
            'context_nip' => 'NIP de l’écurie (contexte)',
            'context_nip_helper' => 'NIP utilisé lors de l’authentification à KSeF — le même que sur les factures.',
            'identifier_type' => 'Type d’identifiant de signature',
            'tab_pfx' => 'PFX / P12',
            'tab_pem' => 'PEM (.crt + .key)',
            'cert_pfx_file' => 'Fichier de certificat (.pfx / .p12)',
            'cert_pfx_password' => 'Mot de passe PFX',
            'cert_pfx_password_helper' => 'Mot de passe utilisé UNIQUEMENT pour l’analyse — N’EST PAS stocké en clair.',
            'cert_pem_crt' => 'Certificat (.crt / .pem)',
            'cert_pem_key' => 'Clé privée (.key / .pem)',
            'cert_pem_password' => 'Mot de passe de la clé (si chiffrée)',
            'cert_subject_cn' => 'Sujet',
            'cert_subject_nip' => 'NIP dans le certificat',
            'cert_issuer' => 'Émetteur',
            'cert_fingerprint' => 'Empreinte SHA-256',
            'cert_valid_to' => 'Valide jusqu’au',
            'cert_type' => 'Type',
        ],
    ],

    'action' => [
        'pfx_saved' => 'Certificat PFX enregistré.',
        'pfx_error_title' => 'Erreur certificat PFX',
        'pem_saved' => 'Certificat PEM enregistré.',
        'pem_error_title' => 'Erreur certificat PEM',
        'saved' => 'Paramètres KSeF enregistrés',
        'cant_read_file' => 'Impossible de lire le fichier de certificat téléversé.',
    ],
];
