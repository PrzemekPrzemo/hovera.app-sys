<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'invoice_data' => 'Dane faktury',
            'buyer' => 'Nabywca',
            'seller' => 'Sprzedawca (snapshot)',
            'dates' => 'Daty',
            'items' => 'Pozycje',
            'notes' => 'Notatki',
        ],
        'label' => [
            'kind' => 'Rodzaj',
            'number' => 'Numer',
            'number_placeholder' => '— nadawany przy wystawieniu —',
            'status' => 'Status',
            'client' => 'Klient',
            'buyer_type' => 'Typ nabywcy',
            'buyer_source' => 'Skąd dane nabywcy',
            'buyer_name' => 'Nazwa / imię i nazwisko',
            'buyer_nip' => 'NIP',
            'buyer_address' => 'Adres',
            'buyer_postal_code' => 'Kod',
            'buyer_city' => 'Miasto',
            'buyer_country' => 'Kraj',
            'seller_name' => 'Nazwa',
            'seller_nip' => 'NIP',
            'seller_address' => 'Adres',
            'seller_postal_code' => 'Kod',
            'seller_city' => 'Miasto',
            'seller_country' => 'Kraj',
            'issued_at' => 'Wystawiona',
            'sale_date' => 'Data sprzedaży',
            'due_at' => 'Termin płatności',
            'item_name' => 'Nazwa',
            'item_quantity' => 'Ilość',
            'item_unit' => 'Jedn.',
            'item_unit_price' => 'Cena j. netto',
            'item_vat' => 'VAT',
            'notes_label' => 'Uwagi',
        ],
        'buyer_type' => [
            'individual' => 'Osoba fizyczna',
            'individual_hint' => 'FV bez NIP-u — tylko imię i nazwisko (osoba nieprowadząca działalności).',
            'company' => 'Firma / przedsiębiorca',
            'company_hint' => 'FV firmowa — wymagany NIP, nazwa, adres.',
        ],
        'buyer_source' => [
            'client' => 'Klient z bazy',
            'client_hint' => 'Wybierz istniejącego klienta — dane nabywcy wypełnią się automatycznie.',
            'adhoc' => 'Jednorazowy odbiorca (ad-hoc)',
            'adhoc_hint' => 'FV dla osoby/firmy która nie jest jeszcze w bazie klientów — wpisz dane ręcznie. Możesz użyć "Pobierz z GUS" do uzupełnienia adresu po NIP-ie.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numer',
            'kind' => 'Rodzaj',
            'issued_at' => 'Wystawiona',
            'client' => 'Nabywca',
            'total' => 'Brutto',
            'status' => 'Status',
            'due_at' => 'Termin',
        ],
        'filter' => [
            'overdue' => 'Po terminie',
        ],
    ],

    'action' => [
        'issue' => [
            'label' => 'Wystaw',
            'success' => 'Faktura wystawiona',
            'failure_title' => 'Nie można wystawić faktury',
        ],
        'correct' => [
            'label' => 'Korekta',
            'success_title' => 'Korekta utworzona',
            'success_body' => 'Otwórz draft :id i edytuj pozycje.',
            'failure_title' => 'Błąd',
        ],
        'ksef' => [
            'label' => 'Wyślij do KSeF',
            'modal_description' => 'Faktura zostanie podpisana certyfikatem stajni i wysłana do KSeF.',
            'auth_success_title' => 'KSeF: uwierzytelnienie udane',
            'auth_success_body' => 'Wysyłka treści faktury w przygotowaniu (PR 4b).',
            'failure_title' => 'KSeF: błąd',
        ],
        'email' => [
            'label' => 'Wyślij na e-mail',
            'modal_description' => 'Wyślemy link do faktury na e-mail klienta. Link działa do 90 dni (lub 14 dni po terminie płatności).',
            'no_email' => 'Brak e-maila klienta',
            'success' => 'Wysłano fakturę na e-mail klienta',
        ],
    ],
];
