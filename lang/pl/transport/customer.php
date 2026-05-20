<?php

declare(strict_types=1);

return [
    'navigation' => 'Klienci',
    'model_label' => 'Klient',
    'plural_label' => 'Klienci',

    'section' => [
        'identification' => 'Dane podstawowe',
        'registry' => 'Rejestry firmowe (NIP / KRS)',
        'registry_description' => 'Po wpisaniu NIP / KRS kliknij ikonę 🔍 aby pobrać dane z publicznych rejestrów MF (Biała Lista) lub KRS. Dane trafią do faktury.',
        'notes' => 'Notatki wewnętrzne',
    ],

    'form' => [
        'label' => [
            'name' => 'Nazwa / imię i nazwisko',
            'company' => 'Pełna nazwa firmy',
            'email' => 'Email',
            'phone' => 'Telefon',
            'tax_id' => 'NIP',
            'krs_number' => 'Numer KRS',
            'address' => 'Adres do faktury',
            'notes' => 'Notatki',
            'last_verified' => 'Ostatnia weryfikacja',
        ],
        'value' => [
            'not_verified' => 'Nie weryfikowano',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'company' => 'Firma',
            'tax_id' => 'NIP',
            'email' => 'Email',
            'phone' => 'Telefon',
            'verified' => 'Zweryfikowany',
            'created_at' => 'Dodano',
        ],
    ],

    'action' => [
        'lookup_nip_tooltip' => 'Pobierz dane z MF Biała Lista',
        'lookup_krs_tooltip' => 'Pobierz dane z KRS',
    ],

    'notify' => [
        'lookup_empty_identifier' => 'Wpisz numer NIP / KRS przed weryfikacją',
        'lookup_success' => 'Dane pobrane z :source',
        'lookup_failed' => 'Nie udało się pobrać danych',
    ],
];
