<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'numbering' => 'Numeracja faktur',
            'numbering_description' => 'Placeholdery: {seq}, {seq:NN} (np. {seq:4} → 0001), {YYYY}, {YY}, {MM}, {M}, {DD}, {prefix}.',
            'seller' => 'Dane sprzedawcy (snapshot na fakturach)',
            'seller_description' => 'Te dane zostaną zapisane na każdej nowej fakturze w momencie utworzenia. Edycja danych stajni nie zmieni już wystawionych FV.',
        ],
        'label' => [
            'template_fv' => 'Wzór FV',
            'template_pro' => 'Wzór Proforma',
            'template_kor' => 'Wzór Korekta',
            'prefix' => 'Prefiks (placeholder {prefix})',
            'prefix_placeholder' => 'np. STW',
            'reset_interval' => 'Reset numeracji',
            'default_due_days' => 'Domyślny termin płatności (dni)',
            'seller_name' => 'Nazwa sprzedawcy',
            'seller_nip' => 'NIP sprzedawcy',
            'seller_address' => 'Adres',
            'seller_postal_code' => 'Kod pocztowy',
            'seller_city' => 'Miasto',
        ],
    ],

    'action' => [
        'saved' => 'Zapisano ustawienia faktur',
    ],
];
