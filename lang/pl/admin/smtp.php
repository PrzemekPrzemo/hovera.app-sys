<?php

declare(strict_types=1);

return [
    'navigation' => 'SMTP (emaile)',
    'title' => 'Konfiguracja SMTP — wysyłka emaili',

    'form' => [
        'section' => [
            'default' => 'Mailer domyślny (master admin, password reset, notyfikacje)',
            'default_description' => 'Używany do: password reset linkow, notyfikacji do master adminów, alertów systemowych, emaili do tenantów (np. weryfikacja firmy transportowej).',
            'transport' => 'Mailer transport (oferty + dispatcher do kierowców)',
            'transport_description' => 'Dedykowany mailer dla emaili wychodzących z modułu transport — oferty do klientów, dispatcher do kierowców, prośby o recenzje. Osobne creds + osobny "From" żeby zachować reputację domeny niezależnie od mailera systemowego.',
            'test' => '🧪 Test wysyłki',
            'test_description' => 'Wyśle email testowy używając AKTUALNIE ZAPISANYCH ustawień (po Save). Jeśli zmieniasz konfig, najpierw Save, potem Test.',
        ],
        'label' => [
            'host' => 'SMTP host',
            'port' => 'Port',
            'username' => 'Username (login)',
            'password' => 'Password',
            'encryption' => 'Szyfrowanie',
            'from_address' => 'From email',
            'from_name' => 'From nazwa',
            'status' => 'Status',
            'test_email' => 'Wyślij testowy email do',
        ],
        'helper' => [
            'host' => 'Np. smtp.gmail.com, smtp.sendgrid.net, smtp-relay.brevo.com',
            'password_leave_blank' => 'Pozostaw puste żeby NIE zmieniać. Wpisanie nowego hasła nadpisze poprzednie.',
            'test_email' => 'Domyślnie Twój email master admina. Sprawdzi czy SMTP faktycznie wysyła.',
        ],
        'encryption' => [
            'none' => 'Bez szyfrowania (niezalecane)',
        ],
        'status' => [
            'configured' => '✓ Skonfigurowane (override .env)',
            'using_env' => '⚠ Używa wartości z .env (nie skonfigurowane w UI)',
        ],
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację SMTP',
        'saved' => 'SMTP zapisane',
        'saved_body' => 'Override .env aktywny. Następny request użyje nowych ustawień.',
        'send_test_button' => 'Wyślij test',
        'test_sent' => 'Test wysłany do :email — sprawdź inbox.',
        'test_failed' => 'Test wysyłki failuje — sprawdź konfig',
        'test_invalid_email' => 'Podaj poprawny adres email do testu',
    ],

    'test_email' => [
        'subject' => 'Hovera SMTP test — działa!',
        'body' => 'To jest email testowy z konfiguracji SMTP w panelu master admin /admin/smtp-settings. '
            .'Jeśli to czytasz, SMTP działa poprawnie. Możesz bezpiecznie używać tego mailera dla password reset, '
            .'notyfikacji do tenantów i emaili z modułu transport.',
    ],
];
