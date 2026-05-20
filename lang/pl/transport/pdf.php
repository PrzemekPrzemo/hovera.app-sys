<?php

declare(strict_types=1);

return [
    'title' => 'Oferta transportowa :number',
    'number_label' => 'OFERTA NR',
    'issued' => 'Wystawiona',
    'valid_until' => 'Ważna do',

    'heading' => 'Oferta transportu koni',
    'subtitle' => 'Wycena obowiązuje na podany termin oraz w okresie ważności.',

    'section' => [
        'customer' => 'Klient',
        'route' => 'Trasa',
        'pricing' => 'Wycena',
        'terms' => 'Warunki',
        'payment' => 'Płatność',
    ],

    'label' => [
        'name' => 'Imię i nazwisko',
        'company' => 'Firma',
        'tax_id' => 'NIP',
        'email' => 'E-mail',
        'phone' => 'Telefon',
        'address' => 'Adres',
        'from' => 'Skąd',
        'to' => 'Dokąd',
        'date' => 'Data',
        'distance' => 'Dystans',
        'duration' => 'Czas przejazdu',
        'round_trip' => 'Z powrotem',
        'component' => 'Pozycja',
        'amount' => 'Kwota',
        'base_cost' => 'Koszt podstawowy',
        'fuel_surcharge' => 'Dopłata paliwowa',
        'extra_horse_fee' => 'Dodatkowe konie: :count × :rate :currency',
        'surcharge' => 'Marża (:percent%)',
        'minimum_adjustment' => 'Dobór do opłaty minimalnej',
        'net_total' => 'Razem netto',
        'vat' => 'VAT (:rate%)',
        'gross_total' => 'Razem brutto',
        'payment_url' => 'Link do płatności',
        'payment_method_label' => 'Metoda',
        'payment_instructions' => 'Instrukcje',
    ],

    'payment_disclaimer' => 'Płatność realizowana BEZPOŚREDNIO do :transporter. Hovera jest pośrednikiem marketplace i NIE przyjmuje płatności.',

    'exchange_rate_footnote' => 'Kwoty w :currency. Kurs przeliczeniowy NBP (tabela A): 1 :currency = :rate PLN, kurs z :date.',

    'value' => [
        'yes' => 'Tak',
        'no' => 'Nie',
    ],

    'footer' => [
        'generated' => 'Dokument wygenerowany przez :app',
    ],
];
