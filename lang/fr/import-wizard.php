<?php

declare(strict_types=1);

return [
    'navigation' => 'Import de données',
    'title' => 'Import de données depuis Excel/CSV',
    'intro' => 'Importez une liste de clients ou de chevaux depuis un fichier Excel ou CSV. Sources prises en charge : exports Nasza Stajnia, Horstable, ou tout fichier dont la première ligne contient les en-têtes.',

    'template' => [
        'clients' => 'Télécharger le modèle — clients',
        'horses' => 'Télécharger le modèle — chevaux',
    ],

    'steps' => [
        'entity' => [
            'title' => 'Que souhaitez-vous importer ?',
            'description' => 'Choisissez le type de données à importer.',
        ],
        'file' => [
            'title' => 'Téléverser le fichier',
            'description' => 'Formats acceptés : .xlsx, .xls, .csv (max 10 Mo).',
        ],
        'mapping' => [
            'title' => 'Correspondance des colonnes',
            'description' => 'Associez les colonnes du fichier aux champs de hovera.',
        ],
        'preview' => [
            'title' => 'Aperçu et import',
            'description' => 'Vérifiez les 5 premières lignes avant de lancer l’import.',
        ],
    ],

    'fields' => [
        'entity' => 'Type de données',
        'file' => 'Fichier de données',
        'clients' => [
            'first_name' => 'Prénom',
            'last_name' => 'Nom',
            'email' => 'E-mail',
            'phone' => 'Téléphone',
            'street' => 'Rue',
            'postal_code' => 'Code postal',
            'city' => 'Ville',
            'tax_id' => 'N° de TVA / SIRET',
            'notes' => 'Notes',
        ],
        'horses' => [
            'name' => 'Nom du cheval',
            'breed' => 'Race',
            'sex' => 'Sexe',
            'color' => 'Robe',
            'birth_date' => 'Date de naissance',
            'microchip' => 'Puce électronique',
            'passport_number' => 'Numéro de passeport',
            'client_email' => 'E-mail du propriétaire',
            'notes' => 'Notes',
        ],
    ],

    'entity' => [
        'clients' => 'Clients',
        'clients_hint' => 'Propriétaires de chevaux / pensionnaires.',
        'horses' => 'Chevaux',
        'horses_hint' => 'Chevaux en pension et chevaux de club.',
    ],

    'skip' => 'ignorer',
    'upload_first' => 'Téléversez un fichier à l’étape précédente pour pouvoir mapper les colonnes.',
    'parse_pending' => 'En attente du fichier…',
    'parse_summary' => ':rows lignes de données détectées sur :cols colonnes.',
    'parse_failed' => 'Impossible de lire le fichier',
    'no_file' => 'Aucun fichier — revenez à l’étape 2.',

    'preview' => [
        'empty' => 'Aucune donnée à afficher.',
        'status' => 'Statut',
        'ok' => 'OK',
        'note' => 'Vous voyez ci-dessus les 5 premières lignes. Les autres seront validées pendant l’import — les lignes en erreur seront ignorées et listées dans le récapitulatif.',
    ],

    'actions' => [
        'import' => 'Importer',
    ],

    'flash' => [
        'success' => ':count enregistrements importés.',
        'failed' => ':count lignes en erreur ignorées.',
    ],

    'result' => [
        'heading' => 'Résultat de l’import',
        'summary' => 'Importés : :ok · Ignorés : :failed.',
    ],
];
