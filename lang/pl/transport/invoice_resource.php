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
    ],

    'form' => [
        'label' => [
            'seller' => 'Sprzedawca',
            'buyer' => 'Nabywca',
            'net_total' => 'Netto',
            'vat_total' => 'VAT',
            'gross_total' => 'Brutto',
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
