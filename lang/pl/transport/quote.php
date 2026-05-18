<?php

declare(strict_types=1);

return [
    'section' => [
        'header' => 'Nagłówek',
        'customer' => 'Klient',
        'route' => 'Trasa',
        'resources' => 'Zasoby (opcjonalne)',
        'pricing' => 'Wycena',
        'terms' => 'Warunki i uwagi',
    ],

    'form' => [
        'label' => [
            'number' => 'Numer',
            'status' => 'Status',
            'valid_until' => 'Ważne do',
            'customer_name' => 'Imię i nazwisko',
            'customer_email' => 'Email',
            'customer_phone' => 'Telefon',
            'customer_company' => 'Firma',
            'customer_tax_id' => 'NIP / VAT ID',
            'customer_address' => 'Adres do faktury',
            'pickup_address' => 'Adres odbioru',
            'dropoff_address' => 'Adres dostarczenia',
            'preferred_date' => 'Data',
            'preferred_time' => 'Godzina',
            'round_trip' => 'Trasa z powrotem',
            'loaded' => 'Z koniem',
            'vehicle' => 'Pojazd',
            'driver' => 'Kierowca',
            'distance_km' => 'Dystans',
            'rate_per_km' => 'Stawka',
            'duration_seconds' => 'Czas (s)',
            'base_cost' => 'Koszt podstawowy',
            'fuel_surcharge' => 'Dopłata paliwowa',
            'minimum_adjustment' => 'Dobór do min.',
            'net_total' => 'Netto',
            'vat_rate' => 'Stawka VAT',
            'vat_amount' => 'Kwota VAT',
            'gross_total' => 'Brutto',
            'currency' => 'Waluta',
            'routing_provider' => 'Źródło trasy',
            'terms' => 'Warunki handlowe',
            'notes' => 'Notatki wewnętrzne',
        ],
        'helper' => [
            'terms' => 'Tekst widoczny dla klienta na ofercie / PDF.',
            'notes' => 'Notatki tylko dla zespołu — nie idą do klienta.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numer',
            'customer' => 'Klient',
            'route' => 'Trasa',
            'preferred_date' => 'Data',
            'gross_total' => 'Brutto',
            'status' => 'Status',
            'created_at' => 'Utworzono',
        ],
    ],

    'action' => [
        'send' => 'Wyślij do klienta',
        'withdraw' => 'Wycofaj ofertę',
        'download_pdf' => 'Pobierz PDF',
    ],

    'notify' => [
        'sent' => 'Oferta wysłana',
        'sent_body' => 'Oferta :number wysłana na :email z PDFem w załączniku.',
        'sent_no_email' => 'Oferta zapisana, ale email nie poszedł — sprawdź konfigurację SMTP "transport".',
        'sent_no_customer_email' => 'Oferta :number gotowa do wysyłki (klient nie ma maila — pobierz PDF i wyślij ręcznie).',
        'withdrawn' => 'Oferta wycofana',
    ],
];
