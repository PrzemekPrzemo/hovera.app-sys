<?php

declare(strict_types=1);

return [
    'types' => [
        'individual' => 'Osoba prywatna',
        'family' => 'Rodzina',
        'organisation' => 'Firma / organizacja',
    ],
    'types_short' => [
        'individual' => 'Os. prywatna',
        'family' => 'Rodzina',
        'organisation' => 'Firma',
    ],

    'form' => [
        'section' => [
            'data' => 'Dane klienta',
            'armir' => 'Identyfikacja właściciela konia (ARMiR)',
            'armir_description' => 'Wymagane dla właścicieli koni zarejestrowanych w Centralnej Bazie Koniowatych. EP (numer producenta nadany przez ARMiR) — jeśli nie ma, wpisz PESEL.',
            'address' => 'Adres',
            'rodo' => 'RODO',
            'notes' => 'Notatki',
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Wklej URL profilu zawodnika z LiveJumping.com — pokażemy statystyki i ostatnie starty.',
        ],
        'label' => [
            'type' => 'Typ',
            'name' => 'Imię i nazwisko / Nazwa',
            'phone' => 'Telefon',
            'tax_id' => 'NIP / VAT ID',
            'armir_producer_id' => 'Nr EP (numer producenta ARMiR)',
            'armir_producer_id_placeholder' => 'np. 026123456789',
            'pesel' => 'PESEL',
            'street' => 'Ulica i numer',
            'postal_code' => 'Kod pocztowy',
            'city' => 'Miasto',
            'country' => 'Kraj',
            'rodo_consent_at' => 'Zgoda RODO udzielona',
            'rodo_consent_source' => 'Źródło zgody',
            'notes' => 'Notatki wewnętrzne',
            'livejumping_profile_url' => 'URL profilu LiveJumping',
            'livejumping_palmares' => 'Palmares',
        ],
        'helper' => [
            'armir_producer_id' => 'Numer producenta nadany w ARMiR przy rejestracji konia.',
            'pesel' => 'Wpisz tylko jeśli właściciel nie ma nadanego EP w ARMiR.',
            'livejumping_profile_url' => 'Skopiuj adres strony profilu z livejumping.com — np. https://livejumping.com/rider/12345/anna-kowalska',
            'livejumping_no_profile' => 'Wklej URL profilu LJ powyżej, aby zobaczyć palmares.',
            'livejumping_fetch_failed' => 'Nie udało się pobrać danych z LiveJumping (sprawdź URL lub spróbuj później).',
        ],
        'stats' => [
            'starts' => 'Starty',
            'wins' => 'Zwycięstwa',
            'placings' => 'Miejsca w czołówce',
            'ranking_points' => 'Punkty rankingowe',
            'recent_results' => 'Ostatnie wyniki',
        ],
        'gus' => [
            'lookup_label' => 'Pobierz z GUS',
            'invalid_nip' => 'Nieprawidłowy NIP (suma kontrolna).',
            'not_found' => 'Nie znaleziono firmy w GUS.',
            'success' => 'Pobrano dane z GUS.',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'type' => 'Typ',
            'phone' => 'Telefon',
            'horses_count' => 'Konie',
            'rodo' => 'RODO',
            'created_at' => 'Dodany',
        ],
    ],

    'action' => [
        'issue_portal_link' => [
            'label' => 'Skopiuj link portalu',
            'modal_heading' => 'Wygenerować link logowania dla :name?',
            'modal_description' => 'Tworzy jednorazowy magic link (TTL 30 min). Możesz go skopiować i wysłać klientowi ręcznie, np. SMS-em lub Messengerem. Nie wymaga maila.',
            'success_title' => 'Link logowania utworzony',
        ],
        'email_portal_link' => [
            'label' => 'Wyślij link na e-mail',
            'modal_heading' => 'Wysłać link logowania do :name?',
            'modal_description' => 'Wyślemy email z linkiem logowania na adres :email. Link działa 30 minut, jednorazowo.',
            'success_title' => 'Link wysłany',
            'success_body' => 'Email z linkiem logowania wysłany na :email.',
            'no_email' => 'Klient nie ma adresu e-mail w profilu.',
        ],
    ],
];
