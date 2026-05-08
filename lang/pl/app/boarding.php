<?php

declare(strict_types=1);

return [
    'vat_rates' => [
        '23' => '23%',
        '8' => '8%',
        '5' => '5%',
        '0' => '0%',
        'zw' => 'zw. (zwolniona)',
        'np' => 'np. (nie podlega)',
    ],

    'form' => [
        'section' => [
            'service' => 'Usługa w cenniku',
            'service_description' => 'Te usługi wybierasz przy każdym koniu (zakładka "Pensja" na karcie konia). Pojawią się w portalu klienta — właściciel widzi za co płaci.',
        ],
        'label' => [
            'name' => 'Nazwa',
            'name_placeholder' => 'np. Siano, Sprzątanie boksu, Transport na zawody',
            'description' => 'Opis (opcjonalnie)',
            'unit' => 'Jednostka',
            'unit_placeholder' => 'szt. / kg / godz. / m-c',
            'frequency' => 'Częstotliwość naliczania',
            'price_net' => 'Cena netto',
            'vat_rate' => 'Stawka VAT',
            'is_active' => 'Aktywna',
            'sort_order' => 'Kolejność',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'frequency' => 'Częstotliwość',
            'price_net' => 'Cena netto',
            'vat' => 'VAT',
            'horses_count' => 'Konie',
            'is_active' => 'Aktywna',
        ],
    ],
];
