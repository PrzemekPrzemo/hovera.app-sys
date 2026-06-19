<?php

declare(strict_types=1);

return [
    'navigation' => 'SMTP (emaile)',
    'title' => 'Konfiguracja SMTP — wysyłka emaili',

    'form' => [
        'section' => [
            'diagnostics' => '🔬 Diagnostyka — aktywny mailer',
            'diagnostics_description' => 'Sprawdź czy konfiguracja SMTP faktycznie obowiązuje. Jeśli „aktywny mailer" pokazuje log/array — maile lądują w storage/logs/laravel.log zamiast wychodzić przez SMTP, mimo zapisanego konfigu.',
            'default' => 'Mailer domyślny SMTP (master admin, password reset, notyfikacje)',
            'default_description' => 'Używany do: password reset linkow, notyfikacji do master adminów, alertów systemowych, emaili do tenantów (np. weryfikacja firmy transportowej). Klasyczny SMTP — Gmail, Postmark, własny serwer pocztowy.',
            'mailgun' => 'Mailgun API (alternatywa do SMTP, EU region)',
            'mailgun_description' => 'Mailgun API — szybsze i bardziej niezawodne niż SMTP, szczególnie przy większych wolumenach. Gdy `secret` jest ustawiony, Mailgun WYGRYWA nad konfiguracją SMTP powyżej. Wymaga zweryfikowanej domeny w panelu Mailgun (Sending → Domain settings → DNS — SPF + DKIM TXT).',
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
            'skip_tls_verify' => 'Pomiń weryfikację certyfikatu TLS (cert hostname mismatch)',
            'from_address' => 'From email',
            'from_name' => 'From nazwa',
            'status' => 'Status',
            'test_email' => 'Wyślij testowy email do',
            'effective_mailer' => 'Stan konfiguracji',
            'mailgun_domain' => 'Mailgun domena (verified)',
            'mailgun_secret' => 'Mailgun API key',
            'mailgun_endpoint' => 'Region Mailgun',
        ],
        'helper' => [
            'host' => 'Np. smtp.gmail.com, smtp.sendgrid.net, smtp-relay.brevo.com',
            'password_leave_blank' => 'Pozostaw puste żeby NIE zmieniać. Wpisanie nowej wartości nadpisze poprzednią.',
            'mailgun_domain' => 'Domena zweryfikowana w panelu Mailgun, np. `hovera.pl` lub subdomen `mg.hovera.pl`.',
            'mailgun_endpoint' => 'EU (api.eu.mailgun.net) dla domen zarejestrowanych w regionie EU. US tylko gdy konto Mailgun jest amerykańskie.',
            'test_email' => 'Domyślnie Twój email master admina. Sprawdzi czy SMTP faktycznie wysyła.',
            'skip_tls_verify' => 'Włącz TYLKO jeśli widzisz "peer certificate CN did not match expected CN" — typowo gdy shared hosting (lh.pl, home.pl, nazwa.pl) serwuje wildcard cert na innej domenie. Wyłącza weryfikację certyfikatu TLS — downgrade ochrony MITM. Akceptowalne dla maili transactional, NIE używaj dla mailerów publicznych.',
        ],
        'encryption' => [
            'none' => 'Bez szyfrowania (niezalecane)',
        ],
        'status' => [
            'configured' => '✓ Skonfigurowane (override .env)',
            'using_env' => '⚠ Używa wartości z .env (nie skonfigurowane w UI)',
            'mailgun_active' => '✓ Mailgun aktywny — wszystkie emaile idą przez Mailgun API',
            'mailgun_inactive' => '⚠ Mailgun nie skonfigurowany — system używa SMTP / .env',
        ],
    ],

    'action' => [
        'save_button' => 'Zapisz konfigurację SMTP',
        'saved' => 'SMTP zapisane',
        'saved_body' => 'Override .env aktywny. Następny request użyje nowych ustawień.',
        'send_test_button' => 'Wyślij test',
        'test_sent' => 'Test wysłany do :email — sprawdź inbox.',
        'test_sent_body' => 'Mailer użyty: :mailer. Jeśli email nie dotarł w ciągu 2 min — sprawdź spam, potem logi (storage/logs/laravel.log) pod kątem błędu SMTP.',
        'test_failed' => 'Test wysyłki failuje — sprawdź konfig',
        'test_invalid_email' => 'Podaj poprawny adres email do testu',
    ],

    'diagnostics' => [
        'effective_mailer' => 'Aktywny mailer',
        'env_mailer' => 'MAIL_MAILER (.env)',
        'override_active' => 'Override z UI',
        'override_yes' => 'aktywny — UI nadpisuje .env',
        'override_no' => 'nieaktywny — używamy .env',
        'from' => 'From',
        'from_missing' => 'brak adresu nadawcy',
        'not_sending' => 'NIE wysyła emaili!',
        'no_host' => 'brak SMTP host',
        'log_mailer_warning' => 'Aktywny mailer to „:mailer" — to znaczy że emaile NIE wychodzą przez SMTP tylko lądują w logu (storage/logs/laravel.log) lub pamięci. Aby naprawić: zapisz konfigurację SMTP poniżej (Save), wtedy AppServiceProvider automatycznie przełączy mailer na „smtp" przy następnym requestzie.',
        'log_mailer_explanation' => 'Konfiguracja SMTP zapisana w UI NIE wystarczy jeśli aktywny mailer to log/array. W tej sytuacji: 1) Wypełnij host/port/username/password/from poniżej, 2) Kliknij „Zapisz konfigurację SMTP", 3) Odśwież tę stronę — diagnostyka powinna pokazać „smtp → <host>". 4) Potem kliknij „Wyślij test".',
    ],

    'test_email' => [
        'subject' => 'Hovera SMTP test — działa!',
        'body' => 'To jest email testowy z konfiguracji SMTP w panelu master admin /admin/smtp-settings. '
            .'Jeśli to czytasz, SMTP działa poprawnie. Możesz bezpiecznie używać tego mailera dla password reset, '
            .'notyfikacji do tenantów i emaili z modułu transport.',
    ],
];
