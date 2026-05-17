<?php

declare(strict_types=1);

return [
    'navigation' => 'LiveJumping',
    'title' => 'LiveJumping.com-Integration',

    'section' => [
        'status' => 'Integrationsstatus',
        'status_help' => 'Aktueller Stand der Partnerschaft mit LiveJumping.com. Solange inaktiv, erscheint keine LJ-Oberfläche in den Stallpaneelen.',
        'credentials' => 'API-Zugangsdaten',
        'credentials_help' => 'Vom LiveJumping-Team im Rahmen der Partnerschaft bereitgestellt. Token wird verschlüsselt gespeichert (AES).',
        'partnership' => 'Partnerschaft starten',
        'partnership_help' => 'Aktivieren Sie diese Option nach einem erfolgreichen Verbindungstest, um die vollständige Integration in allen Ställen freizuschalten.',
    ],

    'field' => [
        'status' => 'Status',
        'connected_at' => 'Verbunden seit',
        'api_url' => 'API-URL',
        'api_url_help' => 'Basis-URL der LiveJumping-Partner-API, ohne Schrägstrich am Ende.',
        'api_token' => 'API-Token',
        'api_token_status' => 'Token gespeichert?',
        'api_token_help' => 'Bearer-Token einfügen; vorhandener wird überschrieben. Leeres Feld = keine Änderung.',
        'enabled' => 'Partnerschaft aktivieren',
        'enabled_help' => 'Bei Aktivierung erscheinen: ein „Sport"-Bereich in Pferde- und Reiterkarten, ein Widget für anstehende Starts auf dem Dashboard und ein Wettbewerbsband im Kalender.',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'configured' => 'konfiguriert',
        'not_configured' => 'nicht konfiguriert',
    ],

    'action' => [
        'test' => 'Verbindung testen',
        'test_ok' => 'Verbindung OK',
        'test_failed' => 'Test fehlgeschlagen',
        'test_missing_creds' => 'URL oder Token fehlen — bitte ausfüllen und erneut versuchen.',
        'cannot_enable_without_token' => 'Speichern Sie zuerst das API-Token, um zu aktivieren.',
        'saved' => 'Einstellungen gespeichert',
        'save_button' => 'Einstellungen speichern',
    ],
];
