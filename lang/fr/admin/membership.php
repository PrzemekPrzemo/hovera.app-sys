<?php

declare(strict_types=1);

return [
    'roles' => [
        'owner' => 'Propriétaire',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'instructor' => 'Moniteur',
        'employee' => 'Employé',
        'vet' => 'Vétérinaire',
        'viewer' => 'Lecture seule',
        // Transport-specific:
        'operator' => 'Opérateur (devis / calculateur / factures / affectation des chauffeurs)',
        'driver' => 'Chauffeur (ses trajets / calendrier / documents)',
    ],

    'form' => [
        'label' => [
            'email' => 'E-mail de l’utilisateur',
            'name' => 'Nom et prénom (optionnel, uniquement pour un nouvel utilisateur)',
            'role' => 'Rôle dans l’écurie',
            'attach_email' => 'E-mail',
            'attach_name' => 'Nom et prénom (si nouvel utilisateur)',
            'attach_role' => 'Rôle',
            'impersonate_reason' => 'Motif de l’imitation (audit RGPD)',
        ],
        'helper' => [
            'email' => 'Si l’utilisateur n’existe pas, il sera créé avec un mot de passe généré.',
            'impersonate_reason' => 'Champ obligatoire. Chaque action effectuée pendant la session d’imitation est marquée dans le journal d’audit de l’écurie.',
        ],
    ],

    'table' => [
        'column' => [
            'email' => 'E-mail',
            'name' => 'Nom',
            'role' => 'Rôle',
            'joined_at' => 'A rejoint le',
            'revoked_at' => 'Révoqué le',
        ],
        'filter' => [
            'status_label' => 'Statut',
            'status_placeholder' => 'Actifs et révoqués',
            'status_true' => 'Uniquement révoqués',
            'status_false' => 'Uniquement actifs',
        ],
    ],

    'action' => [
        'attach' => [
            'label' => 'Ajouter un membre',
            'success_attached_title' => 'Membre ajouté',
            'success_attached_body' => ':email a été ajouté à l’écurie.',
            'success_invited_title' => 'Invitation envoyée',
            'success_invited_body' => 'Invitation envoyée à :email. Le lien expire le :expires.',
        ],
        'revoke' => [
            'label' => 'Révoquer l’accès',
            'success' => 'Accès révoqué',
        ],
        'reactivate' => [
            'label' => 'Réactiver',
            'success' => 'Accès rétabli',
        ],
        'impersonate' => [
            'label' => 'Se connecter en tant que',
            'submit' => 'Démarrer l’imitation',
        ],
    ],
];
