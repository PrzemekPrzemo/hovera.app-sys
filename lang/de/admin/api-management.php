<?php

declare(strict_types=1);

return [
    'tokens' => [
        'navigation' => 'Meine API-Tokens',
        'title' => 'Persönliche API-Tokens des Master-Admins',
        'col' => [
            'name' => 'Name',
            'abilities' => 'Berechtigungen',
            'last_used_at' => 'Zuletzt verwendet',
            'created_at' => 'Erstellt',
            'expires_at' => 'Läuft ab',
            'never' => 'nie',
        ],
        'action' => [
            'generate' => 'Token generieren',
            'generate_submit' => 'Generieren',
            'revoke' => 'Widerrufen',
            'revoke_confirm' => 'Der Token funktioniert ab sofort nicht mehr — alle Skripte mit diesem Token erhalten 401.',
            'revoke_success' => 'Token widerrufen',
        ],
        'form' => [
            'name' => 'Token-Name',
            'name_placeholder' => 'z. B. Monitoring Script',
            'abilities' => 'Berechtigungen (Scopes)',
            'abilities_help' => 'Wählen Sie das Minimum, das für den Betrieb erforderlich ist. "admin-all" gewährt vollen Zugriff.',
            'expiry' => 'Ablauf',
            'expiry_none' => 'Kein Ablauf',
            'expiry_30d' => '30 Tage',
            'expiry_90d' => '90 Tage',
            'expiry_1y' => '1 Jahr',
        ],
        'abilities' => [
            'read-tenants' => 'Reitställe lesen (read-tenants)',
            'read-billing' => 'Billing/Stripe lesen (read-billing)',
            'read-system' => 'Systemmetriken lesen (read-system)',
            'admin-impersonate' => 'Benutzer impersonieren (admin-impersonate)',
            'admin-all' => 'Voller Administratorzugriff (admin-all)',
        ],
        'modal' => [
            'heading' => 'Token generiert',
            'warning' => 'Jetzt kopieren — Sie sehen den Token nicht erneut. Bei Verlust generieren Sie einen neuen.',
            'name_label' => 'Token',
            'copy' => 'In Zwischenablage kopieren',
        ],
    ],

    'tenant_tokens' => [
        'navigation' => 'API-Tokens der Tenants',
        'title' => 'An Tenants ausgestellte API-Tokens',
        'col' => [
            'user' => 'Benutzer',
            'tenant' => 'Reitstall',
            'name' => 'Token-Name',
            'abilities' => 'Berechtigungen',
            'last_used_at' => 'Zuletzt verwendet',
            'created_at' => 'Erstellt',
            'ip' => 'IP',
            'user_agent' => 'User-Agent',
        ],
        'filter' => [
            'tenant' => 'Reitstall',
            'activity' => 'Aktivität',
            'active_30d' => 'Aktiv (30 Tage)',
            'dormant' => 'Inaktiv (keine Aktivität)',
            'any' => 'Beliebig',
            'created_range' => 'Erstellungszeitraum',
        ],
        'action' => [
            'revoke' => 'Widerrufen',
            'revoke_confirm' => 'Der Token funktioniert ab sofort nicht mehr. Die Mobil-App dieses Benutzers muss sich erneut anmelden.',
            'revoke_success' => 'Token widerrufen',
        ],
        'bulk' => [
            'revoke' => 'Auswahl widerrufen',
            'revoked' => ':count Tokens widerrufen',
        ],
    ],

    'webhooks' => [
        'navigation' => 'Tenant-Webhooks',
        'model' => 'Webhook-Abonnement',
        'model_plural' => 'Webhooks',
        'col' => [
            'tenant' => 'Reitstall',
            'url_host' => 'URL-Host',
            'events' => 'Ereignisse',
            'is_active' => 'Aktiv',
            'last_delivery' => 'Letzte Zustellung',
            'last_delivery_at' => 'Zeit der letzten Zustellung',
            'created_at' => 'Erstellt',
        ],
        'form' => [
            'section' => [
                'target' => 'Endpoint und Ereignisse',
                'signing' => 'Anfrage-Signierung',
            ],
            'tenant' => 'Reitstall',
            'is_active' => 'Aktiv',
            'url' => 'Endpoint-URL',
            'url_help' => 'POST an diese URL, wenn eines der ausgewählten Ereignisse eintritt. HTTPS empfohlen.',
            'events' => 'Ereignisse (Events)',
            'secret' => 'HMAC-Secret',
            'secret_regenerated' => 'Neues Secret generiert',
            'signing_help' => 'Jede Anfrage enthält den Header X-Hovera-Signature: sha256=<hex>, berechnet per HMAC über den Body. Der Empfänger sollte die Signatur mit demselben Secret prüfen.',
        ],
        'filter' => [
            'tenant' => 'Reitstall',
            'is_active' => 'Aktiv',
        ],
        'action' => [
            'enable' => 'Aktivieren',
            'disable' => 'Deaktivieren',
            'toggled' => 'Status geändert',
        ],
        'deliveries' => [
            'title' => 'Zustellungshistorie (letzte 50)',
            'col' => [
                'event' => 'Ereignis',
                'attempt' => 'Versuch',
                'status' => 'HTTP-Code',
                'duration' => 'Dauer',
                'delivered_at' => 'Gesendet',
                'error' => 'Fehler',
                'payload' => 'Payload',
            ],
            'action' => [
                'resend' => 'Erneut senden',
                'resent' => 'Erneute Zustellung in Warteschlange',
            ],
        ],
    ],
];
