<?php

declare(strict_types=1);

return [
    'navigation' => 'Faktury',

    'section' => [
        'header' => 'Nagłówek',
        'parties' => 'Strony',
        'amounts' => 'Kwoty',
        'dates' => 'Daty',
        'route' => 'Trasa',
        'notes' => 'Notatki',
        'correction' => 'Korekta faktury',
        'correction_help' => 'Wskaż oryginalną fakturę którą koryguje ta KOR. KSeF wymaga reference '
            .'w XML (`<NrFaKorygowanej>` + `<DataWystFaKorygowanej>`).',
    ],

    'form' => [
        'label' => [
            'seller' => 'Sprzedawca',
            'buyer' => 'Nabywca',
            'net_total' => 'Netto',
            'vat_total' => 'VAT',
            'gross_total' => 'Brutto',
            'corrects_invoice' => 'Faktura korygowana',
        ],
        'helper' => [
            'kind' => 'Wybierz „Korekta" jeśli ta FV koryguje wcześniej wystawioną. '
                .'Niezmienialne po wystawieniu (zmiana kind po wysyłce do KSeF łamie zgodność).',
            'corrects_invoice' => 'Numer oryginalnej FV. Pojawia się w XML w bloku `<DaneFaKorygowanej>`.',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numer',
            'kind' => 'Rodzaj',
            'buyer' => 'Nabywca',
            'issued_at' => 'Wystawiono',
            'due_at' => 'Termin',
            'total' => 'Brutto',
            'status' => 'Status',
        ],
    ],

    'action' => [
        'download_pdf' => 'Pobierz PDF',
        'send_email' => 'Wyślij mailem',
        'mark_paid' => 'Oznacz zapłaconą',
    ],

    'notify' => [
        'sent' => 'Faktura wysłana',
        'sent_body' => 'Faktura :number wysłana na :email z PDFem w załączniku.',
        'no_buyer_email' => 'Nabywca nie ma podanego maila — pobierz PDF i wyślij ręcznie.',
        'email_failed' => 'Wysyłka nieudana',
        'marked_paid' => 'Faktura oznaczona jako zapłacona',
    ],
];
