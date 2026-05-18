<?php

declare(strict_types=1);

return [
    'navigation' => 'Vérification du compte',
    'title' => 'Documents de vérification',

    'status' => [
        'heading' => 'Statut de vérification du compte',
        'pending_body' => 'Pour activer votre compte, veuillez téléverser :count documents manquants. Sans vérification, vous ne pouvez pas envoyer de devis ni de factures.',
        'under_review_body' => 'Tous les documents requis téléversés — l’équipe Hovera vérifie (1–2 jours ouvrés).',
        'verified_body' => 'Compte actif. Vous pouvez envoyer des devis, émettre des factures, recevoir des demandes du marketplace.',
        'rejected_body' => 'Compte refusé. Consultez les remarques et téléversez des versions corrigées.',
        'missing_badge' => ':count manquant(s)',
    ],

    'label' => [
        'required' => 'requis',
        'optional' => 'optionnel',
        'uploaded_at' => 'Téléversé',
        'expires_at' => 'Valide jusqu’au',
        'issued_at' => 'Date d’émission',
        'expired' => 'EXPIRÉ',
        'expiring_soon' => 'expire bientôt',
        'rejection_reason' => 'Motif du refus',
    ],

    'action' => [
        'upload' => 'Téléverser',
        'delete' => 'Supprimer',
    ],

    'confirm' => [
        'delete' => 'Supprimer ce document ? Action irréversible.',
    ],

    'notify' => [
        'uploaded' => 'Document téléversé',
        'deleted' => 'Document supprimé',
        'error' => 'Erreur',
    ],

    'error' => [
        'no_file' => 'Sélectionnez un fichier avant de cliquer sur « Téléverser ».',
        'bad_mime' => 'Format non autorisé. Autorisés : :allowed.',
        'too_large' => 'Fichier trop volumineux. Maximum :limit.',
    ],

    'footer' => [
        'allowed_formats' => 'Acceptés : PDF, JPG, PNG. Maximum 10 Mo par fichier. Tous les fichiers stockés chiffrés en UE.',
    ],
];
