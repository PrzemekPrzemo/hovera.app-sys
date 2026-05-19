<?php

declare(strict_types=1);

return [
    'section' => [
        'title' => 'KSeF (Krajowy System e-Faktur)',
        'description' => 'Integracja KSeF dla faktur transportowych — wystawianych przez Ciebie.',
        'disclaimer' => 'KSeF token zdobywasz w Twoim koncie Krajowego Systemu e-Faktur (mf.gov.pl). '
            .'Hovera tylko przekazuje Twoje faktury — to TWÓJ token, TWOJE NIPy, TWOJA odpowiedzialność '
            .'za zgodność księgową. Hovera nie jest stroną Twoich umów transportowych ani wystawcą '
            .'Twoich faktur (patrz docs/TRANSPORT.md §12).',
        'invoice_title' => 'KSeF — status wysyłki',
        'invoice_description' => 'Informacje o wysyłce do Krajowego Systemu e-Faktur (jeśli włączone).',
    ],

    'form' => [
        'label' => [
            'nip' => 'NIP wystawcy (Twój)',
            'environment' => 'Środowisko KSeF',
            'token' => 'Token autoryzacyjny KSeF',
            'enabled' => 'Włącz integrację KSeF',
            'invoice_status' => 'Status w KSeF',
            'reference_number' => 'Numer referencyjny KSeF',
            'submitted_at' => 'Wysłano',
        ],
        'helper' => [
            'nip' => '10-cyfrowy NIP używany w KSeF. Domyślnie podpowiadamy NIP konta.',
            'token_empty' => 'Wklej token wygenerowany w panelu MF (Twoim koncie KSeF). '
                .'Przechowujemy go zaszyfrowany.',
            'token_set' => 'Token jest zapisany. Wpisz nowy aby zmienić, pozostaw puste aby zachować obecny.',
            'enabled' => 'Po włączeniu pojawi się akcja „Wyślij do KSeF" przy fakturach. '
                .'Nie da się włączyć bez tokenu.',
        ],
        'option' => [
            'environment' => [
                'test' => 'Test (ksef-test.mf.gov.pl)',
                'demo' => 'Demo (ksef-demo.mf.gov.pl)',
                'production' => 'Produkcja (ksef.mf.gov.pl)',
            ],
        ],
    ],

    'action' => [
        'submit' => 'Wyślij do KSeF',
        'submit_tooltip' => 'Wymaga handshake z MF (challenge + szyfrowanie). '
            .'Pierwsza wysyłka po dłuższej przerwie potrwa parę sekund — '
            .'kolejne w ciągu 2h korzystają z cache\'owanej sesji.',
        'submit_confirm' => 'Wysłać tę fakturę do Krajowego Systemu e-Faktur? Operacji nie da się cofnąć.',
        'submit_bulk' => 'Wyślij zaznaczone do KSeF',
        'submit_bulk_confirm' => 'Wyślij zaznaczone faktury (maks. 50) do KSeF? Operacja nieodwracalna.',
        'refresh' => 'Odśwież status z KSeF',
        'test_connection' => 'Test połączenia z KSeF',
    ],

    'notify' => [
        'submitted' => 'Faktura wysłana do KSeF.',
        'submit_failed' => 'Nie udało się wysłać do KSeF.',
        'status_refreshed' => 'Status KSeF odświeżony.',
        'not_configured' => 'KSeF nie jest skonfigurowany.',
        'unknown_error' => 'Nieznany błąd KSeF.',
        'test_ok' => 'Połączenie z KSeF działa.',
        'test_failed' => 'Połączenie z KSeF nie powiodło się.',
        'bulk_done' => 'Wysyłka zbiorcza zakończona.',
        'bulk_done_body' => 'Pomyślnie: :ok. Błędy: :fail.',
    ],

    'status' => [
        'not_submitted' => 'Niewysłane',
        'submitted' => 'Wysłane',
        'accepted' => 'Zaakceptowane',
        'rejected' => 'Odrzucone',
        'error' => 'Błąd',
    ],

    'table' => [
        'column' => [
            'status' => 'KSeF',
        ],
    ],
];
