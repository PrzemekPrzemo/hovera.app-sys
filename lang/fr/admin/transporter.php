<?php

declare(strict_types=1);

return [
    'navigation' => 'Sociétés de transport',

    'model' => [
        'singular' => 'société de transport',
        'plural' => 'Sociétés de transport',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identification',
            'verification' => 'Vérification',
            'verification_description' => 'La société téléverse les documents dans son panel (/transport/transporter-documents). Vérifiez et approuvez ou rejetez avec une note.',
            'subscription' => 'Abonnement',
        ],
        'label' => [
            'tax_id' => 'N° TVA',
            'verification_status' => 'Statut',
            'verified_at' => 'Vérifié le',
            'verification_notes' => 'Notes / motif',
            'rejection_reason' => 'Motif de refus',
            'plan' => 'Plan',
        ],
        'helper' => [
            'verification_status' => 'Modifié uniquement via « Approuver » / « Refuser ».',
            'verification_notes' => 'Visible par la société.',
        ],
    ],

    'table' => [
        'column' => [
            'verification' => 'Vérification',
            'plan' => 'Plan',
            'subscription' => 'Abonnement',
            'last_activity_at' => 'Dernière activité',
            'created_at' => 'Créée',
        ],
    ],

    'action' => [
        'verify' => 'Approuver le compte',
        'reject' => 'Refuser le compte',
    ],

    'notify' => [
        'verified' => 'Compte approuvé',
        'verified_body' => 'Société :name activée. Elle peut envoyer des devis et des factures.',
        'rejected' => 'Compte refusé',
        'rejected_body' => 'Société :name refusée. Elle a reçu un e-mail avec le motif.',
    ],
];
