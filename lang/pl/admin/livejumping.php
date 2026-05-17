<?php

declare(strict_types=1);

return [
    'navigation' => 'LiveJumping',
    'title' => 'Integracja LiveJumping.com',

    'section' => [
        'status' => 'Status integracji',
        'status_help' => 'Aktualny stan partnerskiej współpracy z LiveJumping.com. Dopóki nieaktywna — żaden interfejs LJ nie pojawia się w panelach stajni.',
        'credentials' => 'Dane dostępowe API',
        'credentials_help' => 'Podane przez zespół LiveJumping w ramach umowy partnerskiej. Token szyfrowany w bazie (AES).',
        'partnership' => 'Start współpracy',
        'partnership_help' => 'Włącz tę opcję po pomyślnym teście połączenia, aby aktywować pełną integrację we wszystkich stajniach.',
    ],

    'field' => [
        'status' => 'Status',
        'connected_at' => 'Połączono',
        'api_url' => 'Adres API',
        'api_url_help' => 'Bazowy URL partnerskiego API LiveJumping, bez końcowego slasha.',
        'api_token' => 'Token API',
        'api_token_status' => 'Token zapisany?',
        'api_token_help' => 'Wklej token Bearer; istniejący token zostanie nadpisany. Puste pole = bez zmian.',
        'enabled' => 'Aktywuj współpracę',
        'enabled_help' => 'Po włączeniu w panelach stajni pojawią się: sekcja „Sport" w kartach koni i jeźdźców, widget nadchodzących startów na dashboardzie oraz pasek zawodów w kalendarzu.',
    ],

    'status' => [
        'active' => 'Aktywna',
        'inactive' => 'Wyłączona',
        'configured' => 'skonfigurowany',
        'not_configured' => 'nieskonfigurowany',
    ],

    'action' => [
        'test' => 'Testuj połączenie',
        'test_ok' => 'Połączenie OK',
        'test_failed' => 'Test nieudany',
        'test_missing_creds' => 'Brakuje URL lub tokenu — uzupełnij i spróbuj ponownie.',
        'cannot_enable_without_token' => 'Aby aktywować, najpierw zapisz token API.',
        'saved' => 'Ustawienia zapisane',
        'save_button' => 'Zapisz ustawienia',
    ],
];
