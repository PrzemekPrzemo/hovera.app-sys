<?php

declare(strict_types=1);

return [
    'table' => [
        'column' => [
            'tenant' => 'Écurie',
            'role' => 'Rôle',
            'status' => 'Statut',
            'invited_by' => 'Invité par',
            'expires_at' => 'Expire le',
            'accepted_at' => 'Accepté le',
            'created_at' => 'Envoyé le',
        ],
        'status' => [
            'pending' => 'En attente',
            'accepted' => 'Accepté',
            'expired' => 'Expiré',
        ],
        'filter' => [
            'only_pending' => 'Uniquement en attente',
            'expired' => 'Uniquement expirés',
            'accepted' => 'Uniquement acceptés',
            'tenant' => 'Écurie',
        ],
    ],
    'action' => [
        'resend' => [
            'label' => 'Renvoyer',
            'success' => 'Invitation renvoyée',
        ],
        'revoke' => [
            'label' => 'Révoquer',
            'success' => 'Invitation révoquée',
        ],
        'show_url' => [
            'label' => 'Afficher le lien de connexion',
            'modal_heading' => 'Lien de connexion pour :email',
            'modal_description' => 'Chaque appel génère un NOUVEAU jeton (le précédent est invalidé). Le jeton brut n’est pas stocké en base de données — il n’apparaît ici qu’une seule fois.',
            'success_title' => 'Lien généré — copiez ci-dessous :',
        ],
        'resend_email' => [
            'label' => 'Envoyer par e-mail',
            'success_title' => 'Invitation envoyée à :email',
            'success_body' => "Lien (à copier si l’e-mail n’arrive pas) :\n:url",
        ],
    ],
];
