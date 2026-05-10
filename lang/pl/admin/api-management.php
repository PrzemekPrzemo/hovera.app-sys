<?php

declare(strict_types=1);

return [
    'tokens' => [
        'navigation' => 'Moje tokeny API',
        'title' => 'Osobiste tokeny API mastera',
        'col' => [
            'name' => 'Nazwa',
            'abilities' => 'Uprawnienia',
            'last_used_at' => 'Ostatnio użyty',
            'created_at' => 'Utworzony',
            'expires_at' => 'Wygasa',
            'never' => 'nigdy',
        ],
        'action' => [
            'generate' => 'Generuj token',
            'generate_submit' => 'Wygeneruj',
            'revoke' => 'Rewokuj',
            'revoke_confirm' => 'Token przestanie działać natychmiast — wszystkie skrypty z tym tokenem dostaną 401.',
            'revoke_success' => 'Token zrewokowany',
        ],
        'form' => [
            'name' => 'Nazwa tokena',
            'name_placeholder' => 'np. Monitoring script',
            'abilities' => 'Uprawnienia (scopes)',
            'abilities_help' => 'Wybierz minimum potrzebne do działania. "admin-all" daje pełny dostęp.',
            'expiry' => 'Wygaśnięcie',
            'expiry_none' => 'Bez wygaśnięcia',
            'expiry_30d' => '30 dni',
            'expiry_90d' => '90 dni',
            'expiry_1y' => '1 rok',
        ],
        'abilities' => [
            'read-tenants' => 'Odczyt stajni (read-tenants)',
            'read-billing' => 'Odczyt billing/Stripe (read-billing)',
            'read-system' => 'Odczyt metryk systemu (read-system)',
            'admin-impersonate' => 'Impersonacja użytkowników (admin-impersonate)',
            'admin-all' => 'Pełny dostęp administratora (admin-all)',
        ],
        'modal' => [
            'heading' => 'Token wygenerowany',
            'warning' => 'Skopiuj teraz — nie zobaczysz go ponownie. Jeśli zgubisz, wygeneruj nowy.',
            'name_label' => 'Token',
            'copy' => 'Kopiuj do schowka',
        ],
    ],

    'tenant_tokens' => [
        'navigation' => 'Tokeny API tenantów',
        'title' => 'Tokeny API wystawione tenantom',
        'col' => [
            'user' => 'Użytkownik',
            'tenant' => 'Stajnia',
            'name' => 'Nazwa tokena',
            'abilities' => 'Uprawnienia',
            'last_used_at' => 'Ostatnio użyty',
            'created_at' => 'Utworzony',
            'ip' => 'IP',
            'user_agent' => 'User-Agent',
        ],
        'filter' => [
            'tenant' => 'Stajnia',
            'activity' => 'Aktywność',
            'active_30d' => 'Aktywne (30 dni)',
            'dormant' => 'Uśpione (brak aktywności)',
            'any' => 'Dowolne',
            'created_range' => 'Zakres utworzenia',
        ],
        'action' => [
            'revoke' => 'Rewokuj',
            'revoke_confirm' => 'Token przestanie działać natychmiast. Mobilka tego użytkownika będzie musiała się ponownie zalogować.',
            'revoke_success' => 'Token zrewokowany',
        ],
        'bulk' => [
            'revoke' => 'Rewokuj wybrane',
            'revoked' => 'Zrewokowano :count tokenów',
        ],
    ],

    'webhooks' => [
        'navigation' => 'Webhooki tenantów',
        'model' => 'Subskrypcja webhooka',
        'model_plural' => 'Webhooki',
        'col' => [
            'tenant' => 'Stajnia',
            'url_host' => 'Host URL',
            'events' => 'Wydarzeń',
            'is_active' => 'Aktywny',
            'last_delivery' => 'Ostatnia dostawa',
            'last_delivery_at' => 'Czas ostatniej dostawy',
            'created_at' => 'Utworzony',
        ],
        'form' => [
            'section' => [
                'target' => 'Endpoint i wydarzenia',
                'signing' => 'Podpisywanie żądań',
            ],
            'tenant' => 'Stajnia',
            'is_active' => 'Aktywny',
            'url' => 'URL endpointu',
            'url_help' => 'POST na ten URL gdy wystąpi któreś z wybranych wydarzeń. HTTPS zalecane.',
            'events' => 'Wydarzenia (events)',
            'secret' => 'Sekret HMAC',
            'secret_regenerated' => 'Wygenerowano nowy sekret',
            'signing_help' => 'Każde żądanie ma nagłówek X-Hovera-Signature: sha256=<hex> liczony HMAC-em po body. Odbiorca powinien zweryfikować podpis tym samym sekretem.',
        ],
        'filter' => [
            'tenant' => 'Stajnia',
            'is_active' => 'Aktywne',
        ],
        'action' => [
            'enable' => 'Włącz',
            'disable' => 'Wyłącz',
            'toggled' => 'Stan zmieniony',
        ],
        'deliveries' => [
            'title' => 'Historia dostaw (50 ostatnich)',
            'col' => [
                'event' => 'Wydarzenie',
                'attempt' => 'Próba',
                'status' => 'Kod HTTP',
                'duration' => 'Czas',
                'delivered_at' => 'Wysłano',
                'error' => 'Błąd',
                'payload' => 'Payload',
            ],
            'action' => [
                'resend' => 'Wyślij ponownie',
                'resent' => 'Ponowna dostawa zakolejkowana',
            ],
        ],
    ],
];
