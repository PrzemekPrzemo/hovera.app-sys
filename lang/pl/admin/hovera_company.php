<?php

declare(strict_types=1);

return [
    'navigation' => 'Dane firmy hovera',
    'title' => 'Dane firmy hovera (sprzedawca na fakturach)',

    'section' => [
        'identity' => 'Dane identyfikacyjne',
        'identity_help' => 'Wykorzystywane jako dane sprzedawcy na fakturach SaaS wystawianych stajniom (KSeF, PDF, FA(3) XML).',
        'address' => 'Adres siedziby',
        'contact' => 'Kontakt',
        'bank' => 'Rachunek bankowy',
        'bank_help' => 'IBAN trafia na faktury jako konto do przelewu (gdy klient płaci tradycyjnym przelewem zamiast Stripe/P24).',
    ],

    'field' => [
        'name' => 'Nazwa firmy',
        'legal_form' => 'Forma prawna',
        'nip' => 'NIP',
        'regon' => 'REGON',
        'krs' => 'KRS',
        'court' => 'Sąd rejestrowy',
        'capital' => 'Kapitał zakładowy',
        'street' => 'Ulica i numer',
        'postal_code' => 'Kod pocztowy',
        'city' => 'Miasto',
        'country' => 'Kraj (kod ISO)',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'bank_name' => 'Nazwa banku',
        'iban' => 'IBAN',
        'swift' => 'SWIFT/BIC',
    ],

    'action' => [
        'save_button' => 'Zapisz dane firmy',
        'saved' => 'Dane firmy zapisane.',
    ],
];
