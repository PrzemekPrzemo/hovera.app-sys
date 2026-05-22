<?php

declare(strict_types=1);

return [
    'navigation' => 'Status integracji',
    'title' => 'Status integracji zewnętrznych',
    'hint' => 'Snapshot ładuje się instant przy wejściu. „Sprawdź X" wykonuje live ping (max ~3s, może zwrócić timeout gdy zewnętrzna usługa pada).',

    'status' => [
        'ok' => '✓ OK',
        'degraded' => '⚠ Degraded',
        'error' => '✕ Błąd',
        'not_configured' => '○ Nieskonfigurowane',
        'unknown' => '? Nieznany',
    ],

    'label' => [
        'db_central' => 'Baza danych (central)',
    ],

    'detail' => [
        'configured' => 'Skonfigurowane (instant check).',
        'live_ok' => 'Live ping OK.',
        'live_no_response' => 'Live ping bez odpowiedzi (API zwróciło null).',
        'not_configured_gus' => 'Master admin nie wpisał klucza GUS w /admin/company-lookup-settings.',
        'not_configured_ceidg' => 'Master admin nie wpisał tokenu CEIDG w /admin/company-lookup-settings.',
        'vies_default' => 'Domyślny endpoint Komisji Europejskiej (publiczny).',
        'vies_custom_url' => 'Override URL: :url',
        'nbp_no_cache' => 'Brak cache\'owanych kursów — pierwszy fetch wykona się gdy ktoś wystawi FV walutową.',
        'nbp_last_sync' => 'Ostatni sync: :code @ :date',
        'ksef_central_missing' => 'Brakuje NIP-u Hovera lub certyfikatu w SystemSetting.',
        'smtp_no_host' => 'Brak hosta SMTP — sprawdź /admin/smtp-settings.',
        'smtp_host' => 'Host: :host',
        'db_responding' => 'Połączenie odpowiada (SELECT 1).',
    ],

    'action' => [
        'refresh_all' => 'Odśwież snapshot',
        'ping_gus' => 'Sprawdź GUS',
        'ping_ceidg' => 'Sprawdź CEIDG',
        'ping_vies' => 'Sprawdź VIES',
    ],
];
