<?php

declare(strict_types=1);

return [
    'navigation' => 'LiveJumping',
    'title' => 'Intégration LiveJumping.com',

    'section' => [
        'status' => 'Statut de l’intégration',
        'status_help' => 'État actuel du partenariat avec LiveJumping.com. Tant qu’il est inactif, aucune interface LJ n’apparaît dans les panneaux des écuries.',
        'credentials' => 'Identifiants API',
        'credentials_help' => 'Fournis par l’équipe LiveJumping dans le cadre du partenariat. Le token est stocké chiffré (AES).',
        'partnership' => 'Démarrer le partenariat',
        'partnership_help' => 'Activez cette option après un test de connexion réussi pour activer l’intégration complète dans toutes les écuries.',
    ],

    'field' => [
        'status' => 'Statut',
        'connected_at' => 'Connecté le',
        'api_url' => 'URL de l’API',
        'api_url_help' => 'URL de base de l’API partenaire LiveJumping, sans slash final.',
        'api_token' => 'Token API',
        'api_token_status' => 'Token enregistré ?',
        'api_token_help' => 'Collez le token Bearer ; l’existant sera écrasé. Champ vide = pas de changement.',
        'enabled' => 'Activer le partenariat',
        'enabled_help' => 'Une fois activé, les panneaux des écuries affichent : une section « Sport » dans les fiches chevaux et cavaliers, un widget de starts à venir sur le tableau de bord, et un bandeau de concours dans l’agenda.',
    ],

    'status' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'configured' => 'configuré',
        'not_configured' => 'non configuré',
    ],

    'action' => [
        'test' => 'Tester la connexion',
        'test_ok' => 'Connexion OK',
        'test_failed' => 'Test échoué',
        'test_missing_creds' => 'URL ou token manquants — complétez et réessayez.',
        'cannot_enable_without_token' => 'Enregistrez d’abord le token API pour activer.',
        'saved' => 'Paramètres enregistrés',
        'save_button' => 'Enregistrer les paramètres',
    ],
];
