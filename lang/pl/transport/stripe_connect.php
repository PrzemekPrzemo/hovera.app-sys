<?php

declare(strict_types=1);

return [
    'payment_method_label' => 'Stripe (karta / BLIK / Przelewy24)',

    'section' => [
        'title' => 'Stripe Connect Express (online płatności)',
        'description' => 'Aktywacja jednym klikiem — własne konto Stripe Express, pieniądze idą bezpośrednio do Ciebie. Kup-online dla każdej oferty automatycznie.',
        'disclaimer' => 'Stripe Connect Express: TWOJE konto Stripe, TWOJA umowa z Stripe (KYC u Stripe). Hovera tylko technicznie umożliwia checkout — pieniądze idą bezpośrednio do Ciebie. Hovera może (ale domyślnie nie pobiera) prowizji od transakcji — patrz §15 regulaminu marketplace.',
    ],

    'form' => [
        'label' => [
            'status' => 'Stan integracji',
        ],
    ],

    'status' => [
        'none' => 'Niepołączone',
        'pending' => 'W trakcie weryfikacji u Stripe',
        'enabled' => 'Aktywne — możesz przyjmować płatności',
        'restricted' => 'Ograniczone — uzupełnij dane u Stripe',
        'rejected' => 'Odrzucone — skontaktuj się z supportem Stripe',
    ],

    'action' => [
        'connect' => 'Połącz konto Stripe',
        'refresh_status' => 'Sprawdź status',
        'open_dashboard' => 'Otwórz dashboard Stripe',
        'admin_sync' => 'Sprawdź status Stripe',
    ],

    'notify' => [
        'onboard_failed' => 'Nie udało się rozpocząć onboardingu Stripe.',
        'status_sync_failed' => 'Nie udało się zsynchronizować statusu Stripe.',
        'dashboard_failed' => 'Nie udało się wygenerować linku do dashboardu Stripe.',
        'refreshed' => 'Status Stripe zaktualizowany.',
        'status_none' => 'Brak konta Stripe — kliknij „Połącz konto Stripe".',
        'status_pending' => 'KYC w toku — Stripe weryfikuje dane firmy. Spróbuj za chwilę.',
        'status_enabled' => 'Konto Stripe aktywne — możesz wystawiać oferty z płatnością online.',
        'status_restricted' => 'Stripe ograniczył konto — sprawdź dashboard i uzupełnij brakujące dane.',
        'status_rejected' => 'Stripe odrzucił konto — kontakt z supportem Stripe wymagany.',
    ],
];
