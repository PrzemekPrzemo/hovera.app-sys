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

    // @todo native review — traduction automatique depuis PL/EN.
    'section' => [
        'pwl_required' => 'Documents PWL (obligatoires pour la vérification)',
        'pwl_optional' => 'Documents optionnels',
        'legacy' => 'Documents legacy (ne comptent pas pour PWL)',
    ],

    'helper' => [
        'pwl_authorization_choice' => 'Choisir Type 1 OU Type 2 — selon le profil des transports. Le Type 2 (> 8h) couvre aussi le Type 1.',
        'pwl_vehicle_per_vehicle' => 'Émis par véhicule. Pour une flotte : téléverser un PDF fusionné couvrant tous les véhicules.',
        'wash_log_period' => 'Tenir à jour — les entrées de plus de 12 mois sont considérées obsolètes.',
    ],

    'checklist' => [
        'heading' => 'Checklist des documents PWL obligatoires',
        'progress' => ':done sur :total documents vérifiés',
        'missing_intro' => 'Manquant(s) :',
        'all_complete' => 'Tous les documents obligatoires vérifiés.',
        'pwl_authorization_alternative' => 'Autorisation PWL (Type 1 OU Type 2)',
    ],

    'admin' => [
        'verify_doc' => 'Approuver le document',
        'reject_doc' => 'Rejeter le document',
        'verify_doc_confirm' => 'Approuver ce document ? Une fois approuvé, le transporteur ne peut plus le supprimer.',
        'rejection_reason_required' => 'Motif de rejet (visible par le transporteur)',
        'notify_doc_verified' => 'Document approuvé',
        'notify_doc_rejected' => 'Document rejeté',
        'cannot_verify_tenant' => 'Veuillez d’abord vérifier tous les documents PWL obligatoires (:done/:total). Voir la checklist ci-dessous.',
    ],

    'expiry_notify' => [
        'subject' => 'Le document :type expire dans :days jours',
        'greeting' => 'Bonjour,',
        'intro' => 'Le document « :type » pour la société :name expire le :date (dans :days jours).',
        'cta' => 'Téléversez-en un nouveau dans le panneau — sinon votre compte peut être suspendu temporairement à la date d’expiration.',
        'action' => 'Ouvrir les documents',
    ],
];
