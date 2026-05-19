<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'identification' => 'Identification',
            'location' => 'Localisation',
            'subscription' => 'Abonnement',
            'branding' => 'Identité visuelle',
            'branding_description' => 'Utilisée sur la page publique /s/{slug} et dans les e-mails.',
            'public_profile' => 'Profil public',
            'public_profile_description' => 'Informations affichées sur la page publique de l’écurie /s/{slug}.',
            'database' => 'Base de données',
        ],
        'label' => [
            'type' => 'Type de locataire',
            'tax_id' => 'NIP / N° de TVA',
            'plan' => 'Plan',
            'primary_color' => 'Couleur principale',
            'logo_url' => 'URL du logo',
            'public_description' => 'Description de l’écurie',
            'public_email' => 'E-mail de contact (public)',
            'public_phone' => 'Téléphone de contact',
            'public_address' => 'Adresse',
            'public_website' => 'Site web',
        ],
        'option' => [
            'type' => [
                'stable' => 'Écurie équestre',
                'transporter' => 'Société de transport',
            ],
        ],
        'helper' => [
            'slug' => 'Immuable. Utilisé dans les URLs et le nom de base de données.',
            'type' => 'Détermine le panneau après connexion (Écurie → /app, Transport → /transport) et les plans disponibles. Immuable après création.',
            'plan' => 'Liste des plans filtrée par le type de locataire sélectionné.',
        ],
    ],

    'notify' => [
        'created_stable' => 'Écurie créée',
        'created_transporter' => 'Société de transport créée',
        'created_body' => 'La base de données :db a été initialisée.',
    ],

    'table' => [
        'column' => [
            'type' => 'Type',
            'country' => 'Pays',
            'plan' => 'Plan',
            'db_name' => 'Base de données',
            'created_at' => 'Créée le',
        ],
        'filter' => [
            'type' => 'Type de locataire',
        ],
    ],

    'action' => [
        'suspend' => [
            'label' => 'Suspendre',
            'notification_title' => 'Écurie suspendue',
        ],
        'reactivate' => [
            'label' => 'Réactiver',
            'notification_title' => 'Écurie réactivée',
        ],
        'soft_delete' => [
            'label' => 'Soft delete',
        ],
        'login_as_owner' => [
            'label' => 'Se connecter en tant qu’écurie',
            'reason_label' => 'Motif de l’imitation (audit RGPD)',
            'reason_helper' => 'Obligatoire. La session est enregistrée dans impersonation_sessions et audit_log_master.',
            'submit' => 'Démarrer l’imitation',
            'no_user_title' => 'Aucun utilisateur actif pour cette écurie',
            'no_user_body' => 'Ajoutez d’abord un membre de l’équipe ou invitez le propriétaire.',
        ],
        'seed_demo' => [
            'label' => 'Charger des données de démo',
            'modal_heading' => 'Charger des données de démo dans :name ?',
            'modal_description' => 'Ajoute 14 chevaux, 6 clients, 12 boxes, le calendrier, les factures et le reste du jeu de démo. S’applique à la base de données du tenant.',
            'fresh_label' => 'Effacer les données existantes (DROP de toutes les tables)',
            'fresh_helper' => 'ATTENTION : supprime toutes les données actuelles de l’écurie avant le seeding.',
            'success_title' => 'Données de démo chargées',
            'success_body' => 'L’écurie :name dispose désormais du jeu de démo complet.',
            'failure_title' => 'Échec du chargement de la démo',
        ],
        'destroy' => [
            'label' => 'Drop database',
            'modal_heading' => 'Supprimer définitivement l’écurie',
            'modal_description' => 'Cette opération est IRRÉVERSIBLE. La base de données :db et le compte MySQL :user seront supprimés physiquement.',
            'confirm_slug_label' => 'Saisissez le slug de l’écurie pour confirmer',
            'slug_mismatch' => 'Le slug ne correspond pas.',
            'success_title' => 'Écurie supprimée définitivement',
        ],
    ],
];
