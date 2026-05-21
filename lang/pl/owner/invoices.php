<?php

declare(strict_types=1);

return [
    'autobilling' => [
        'line' => [
            'box' => 'Pensjonat boksu :box — :horse',
        ],
    ],

    'navigation' => 'Faktury',
    'model' => [
        'singular' => 'Faktura',
        'plural' => 'Faktury',
    ],

    'list' => [
        'title' => 'Faktury od stajni',
        'description' => 'Faktury wystawione przez stajnie goszczące Twoje konie. Drafty są ukryte do czasu wystawienia.',
        'empty_heading' => 'Brak faktur',
        'empty_description' => 'Gdy stajnia wystawi pierwszą fakturę za pensjonat, pojawi się tutaj.',
    ],

    'show' => [
        'title' => 'Faktura :number',
        'title_draft' => 'Faktura (draft)',
        'back_to_list' => 'Wróć do listy',
    ],

    'table' => [
        'number' => 'Numer',
        'stable' => 'Stajnia',
        'kind' => 'Typ',
        'status' => 'Status',
        'issued_at' => 'Data wystawienia',
        'due_at' => 'Termin płatności',
        'period' => 'Okres',
        'horse' => 'Koń',
        'total' => 'Razem',
        'actions' => 'Akcje',
        'view' => 'Zobacz',
    ],

    'section' => [
        'meta' => 'Dane faktury',
        'seller' => 'Sprzedawca',
        'buyer' => 'Nabywca',
        'items' => 'Pozycje',
        'totals' => 'Podsumowanie',
        'notes' => 'Uwagi',
    ],

    'field' => [
        'number' => 'Numer',
        'kind' => 'Typ',
        'status' => 'Status',
        'issued_at' => 'Data wystawienia',
        'sale_date' => 'Data sprzedaży',
        'due_at' => 'Termin płatności',
        'paid_at' => 'Data płatności',
        'period' => 'Okres rozliczeniowy',
        'nip' => 'NIP',
        'address' => 'Adres',
        'subtotal' => 'Netto razem',
        'vat' => 'VAT razem',
        'total' => 'Brutto razem',
    ],

    'item' => [
        'position' => 'Lp.',
        'name' => 'Nazwa',
        'quantity' => 'Ilość',
        'unit_price' => 'Cena netto',
        'vat_rate' => 'VAT',
        'net' => 'Netto',
        'total' => 'Brutto',
    ],

    'action' => [
        'download_pdf' => 'Pobierz PDF',
        'download_pdf_unavailable' => 'PDF wkrótce',
        'pay_online' => 'Zapłać online',
        'pay_online_unavailable' => 'Płatność online wkrótce',
    ],

    'api' => [
        'pdf_not_implemented' => 'PDF generation będzie dostępne w kolejnej iteracji.',
        'pay_not_implemented' => 'Płatność online będzie dostępna w kolejnej iteracji.',
    ],
];
