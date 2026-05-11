<?php

declare(strict_types=1);

return [
    'navigation' => 'Przelewy24',
    'title' => 'Configuration Przelewy24',

    'section' => [
        'account' => 'Compte Przelewy24',
        'account_help' => 'Informations de la boutique depuis le panneau Przelewy24 (panel.przelewy24.pl → Mes boutiques → Données de la boutique).',
        'secrets' => 'Clés API',
        'secrets_help' => 'Les clés sont chiffrées (Laravel Crypt) et ne sont jamais affichées en clair après enregistrement. Pour les modifier, saisissez une nouvelle valeur — un champ vide n’écrase pas l’existant.',
        'webhook' => 'URLs à configurer dans le panneau P24',
        'webhook_help' => 'Collez ces URLs dans le panneau Przelewy24 → Mes boutiques → Configuration → Paramètres de notification / URL de retour.',
    ],

    'field' => [
        'env' => 'Environnement',
        'merchant_id' => 'ID marchand',
        'merchant_id_help' => 'Numéro à 6 chiffres du panneau P24 (par exemple 168172).',
        'pos_id' => 'POS ID',
        'pos_id_help' => 'Le plus souvent identique à l’ID marchand.',
        'api_key' => 'Clé API (secret)',
        'api_key_help' => 'Clé pour les rapports — panneau P24 → Mes boutiques → Configuration → Clés.',
        'api_key_status' => 'Statut de la clé API',
        'crc' => 'Clé CRC (secret)',
        'crc_help' => 'Clé de signature des transactions — panneau P24 → Mes boutiques → Configuration → Clés → CRC.',
        'crc_status' => 'Statut de la clé CRC',
        'webhook_url' => 'Webhook (notifications de statut)',
        'return_url' => 'URL de retour après paiement',
    ],

    'env' => [
        'sandbox' => 'Sandbox (test)',
        'production' => 'Production',
    ],

    'status' => [
        'configured' => 'Configuré',
        'not_configured' => 'Non configuré',
    ],

    'action' => [
        'save_button' => 'Enregistrer la configuration',
        'saved' => 'Configuration Przelewy24 enregistrée.',
    ],
];
