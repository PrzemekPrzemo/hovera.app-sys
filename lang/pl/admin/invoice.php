<?php

declare(strict_types=1);

return [
    'navigation' => 'Faktury SaaS',
    'model' => 'Faktura SaaS',
    'model_plural' => 'Faktury SaaS',

    'kind' => [
        'regular' => 'Zwykła (FV)',
        'proforma' => 'Proforma',
        'correction' => 'Korekta',
    ],

    'form' => [
        'section' => [
            'basics' => 'Podstawowe dane',
            'amounts' => 'Kwoty',
            'dates' => 'Daty',
        ],
        'label' => [
            'tenant' => 'Stajnia (kupujący)',
            'number' => 'Numer faktury',
            'kind' => 'Typ',
            'subtotal' => 'Netto (grosze)',
            'vat_rate' => 'Stawka VAT (%)',
        ],
    ],

    'table' => [
        'column' => [
            'number' => 'Numer',
            'tenant' => 'Stajnia',
            'issued_at' => 'Wystawiona',
            'total' => 'Suma brutto',
            'status' => 'Status',
            'ksef_status' => 'KSeF',
        ],
    ],

    'action' => [
        'issue_manual' => 'Wystaw FV ręcznie',
        'send_p24_link' => 'Wyślij link P24',
        'p24_link_generated' => 'Link Przelewy24 wygenerowany',
        'p24_link_failed' => 'Nie udało się wygenerować linku P24',
        'send_to_ksef' => 'Wyślij do KSeF',
        'ksef_sent' => 'Wysłano do KSeF',
        'ksef_failed' => 'Wysyłka do KSeF nieudana',
        'ksef_reference' => 'Numer referencyjny KSeF',
        'download_pdf' => 'Pobierz PDF',
        'pdf_stub_title' => 'Generacja PDF wstrzymana',
        'pdf_stub_body' => 'Pełna generacja PDF FV wymaga dompdf/snappy — zostanie dodana w follow-up PR.',
        'resend_email' => 'Wyślij e-mail ponownie',
    ],

    'p24_return' => [
        'paid' => 'Płatność faktury :number została potwierdzona.',
        'pending' => 'Dziękujemy! Płatność faktury :number jest weryfikowana — zwykle trwa to kilka minut.',
        'unknown' => 'Nie rozpoznano faktury — sprawdź mail z potwierdzeniem.',
    ],
];
