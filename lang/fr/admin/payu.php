<?php

declare(strict_types=1);

return [
    'navigation' => 'PayU',
    'title' => 'Configuration PayU',

    'section' => [
        'account' => 'Compte PayU',
        'account_help' => 'Détails du point de vente depuis votre tableau de bord PayU (panel.payu.com → Ma boutique → Configuration → Points de vente).',
        'secrets' => 'Clés API',
        'secrets_help' => 'Les clés sont chiffrées (Laravel Crypt) et ne sont jamais affichées en clair après l\'enregistrement. Pour modifier — saisissez une nouvelle valeur, un champ vide n\'écrase pas.',
        'webhook' => 'URLs à configurer dans le tableau de bord PayU',
        'webhook_help' => 'Collez l\'URL du webhook dans PayU → Points de vente → Configuration → URL de notification (notifyUrl).',
    ],

    'field' => [
        'env' => 'Environnement',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Numéro du point de vente (merchantPosId) — tableau de bord PayU → Ma boutique → Points de vente.',
        'oauth_client_id' => 'OAuth Client ID',
        'oauth_client_id_help' => 'Identifiant client OAuth pour l\'autorisation de l\'API REST — PayU → Points de vente → Configuration → Protocole REST API.',
        'oauth_client_secret' => 'OAuth Client Secret',
        'oauth_client_secret_help' => 'Secret OAuth — échangé contre un access_token via grant_type=client_credentials.',
        'oauth_client_secret_status' => 'Statut du OAuth Client Secret',
        'md5_key' => 'Deuxième clé (MD5)',
        'md5_key_help' => 'Clé utilisée pour vérifier la signature du webhook (en-tête OpenPayU-Signature). Tableau de bord PayU → Clés de configuration.',
        'md5_key_status' => 'Statut de la clé MD5',
        'second_key' => 'Deuxième clé',
        'second_key_help' => 'Clé optionnelle utilisée pour la vérification du callback de statut en flux formulaire hérité. La plupart des intégrations n\'en ont pas besoin — laisser vide.',
        'second_key_status' => 'Statut de la deuxième clé',
        'webhook_url' => 'URL du webhook (notifications de statut)',
        'return_url' => 'URL de retour après paiement',
    ],

    'env' => [
        'sandbox' => 'Bac à sable (secure.snd.payu.com)',
        'production' => 'Production (secure.payu.com)',
    ],

    'status' => [
        'configured' => 'Configuré',
        'not_configured' => 'Non configuré',
    ],

    'action' => [
        'save_button' => 'Enregistrer la configuration',
        'saved' => 'Configuration PayU enregistrée.',
    ],
];
