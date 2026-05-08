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
            'address' => 'Adres',
            'rodo' => 'RODO',
            'notes' => 'Notatki',
        ],
        'label' => [
            'type' => 'Typ',
            'name' => 'Imię i nazwisko / Nazwa',
            'phone' => 'Telefon',
            'tax_id' => 'NIP / VAT ID',
            'street' => 'Ulica i numer',
            'postal_code' => 'Kod pocztowy',
            'city' => 'Miasto',
            'country' => 'Kraj',
            'rodo_consent_at' => 'Zgoda RODO udzielona',
            'rodo_consent_source' => 'Źródło zgody',
            'notes' => 'Notatki wewnętrzne',
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
            'label' => 'Wygeneruj link portalu',
            'modal_heading' => 'Wygenerować link logowania dla :name?',
            'modal_description' => 'Tworzy jednorazowy magic link (TTL 30 min). Możesz go skopiować i wysłać klientowi ręcznie, np. SMS-em lub Messengerem. Nie wymaga maila.',
            'success_title' => 'Link logowania utworzony',
        ],
    ],
];
