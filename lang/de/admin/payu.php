<?php

declare(strict_types=1);

return [
    'navigation' => 'PayU',
    'title' => 'PayU-Konfiguration',

    'section' => [
        'account' => 'PayU-Konto',
        'account_help' => 'Verkaufsstellendaten aus dem PayU-Dashboard (panel.payu.com → Mein Shop → Konfiguration → Verkaufsstellen).',
        'secrets' => 'API-Schlüssel',
        'secrets_help' => 'Schlüssel sind verschlüsselt (Laravel Crypt) und werden nach dem Speichern nie im Klartext angezeigt. Zum Ändern — neuen Wert eingeben, ein leeres Feld überschreibt nicht.',
        'webhook' => 'URLs zur Konfiguration im PayU-Dashboard',
        'webhook_help' => 'Fügen Sie die Webhook-URL in PayU → Verkaufsstellen → Konfiguration → Benachrichtigungs-URL (notifyUrl) ein.',
    ],

    'field' => [
        'env' => 'Umgebung',
        'pos_id' => 'POS-ID',
        'pos_id_help' => 'Verkaufsstellennummer (merchantPosId) — PayU-Dashboard → Mein Shop → Verkaufsstellen.',
        'oauth_client_id' => 'OAuth-Client-ID',
        'oauth_client_id_help' => 'OAuth-Client-Kennung für REST-API-Autorisierung — PayU → Verkaufsstellen → Konfiguration → REST-API-Protokoll.',
        'oauth_client_secret' => 'OAuth-Client-Secret',
        'oauth_client_secret_help' => 'OAuth-Secret — wird über grant_type=client_credentials gegen access_token getauscht.',
        'oauth_client_secret_status' => 'OAuth-Client-Secret-Status',
        'md5_key' => 'Zweiter Schlüssel (MD5)',
        'md5_key_help' => 'Schlüssel zur Verifizierung der Webhook-Signatur (OpenPayU-Signature Header). PayU-Dashboard → Konfigurationsschlüssel.',
        'md5_key_status' => 'MD5-Schlüssel-Status',
        'second_key' => 'Zweiter Schlüssel',
        'second_key_help' => 'Optionaler Schlüssel für Legacy-Formularflow-Status-Callback-Verifizierung. Die meisten Integrationen benötigen ihn nicht — leer lassen.',
        'second_key_status' => 'Zweiter-Schlüssel-Status',
        'webhook_url' => 'Webhook-URL (Statusbenachrichtigungen)',
        'return_url' => 'Rückkehr-URL nach Zahlung',
    ],

    'env' => [
        'sandbox' => 'Sandbox (secure.snd.payu.com)',
        'production' => 'Produktion (secure.payu.com)',
    ],

    'status' => [
        'configured' => 'Konfiguriert',
        'not_configured' => 'Nicht konfiguriert',
    ],

    'action' => [
        'save_button' => 'Konfiguration speichern',
        'saved' => 'PayU-Konfiguration gespeichert.',
    ],
];
