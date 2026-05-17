<?php

declare(strict_types=1);

return [
    'today' => [
        'bookings' => 'Rezerwacje dziś',
        'bookings_desc' => 'Aktywne wpisy w kalendarzu',

        'vacant_boxes' => 'Wolne boksy',
        'vacant_boxes_desc' => 'Aktywne, z miejscem',

        'overdue_care' => 'Przeterminowane zabiegi',
        'overdue_care_desc' => 'Szczepienia / kucie / dentysta po terminie',

        'unpaid_invoices' => 'Niezapłacone FV',
        // Polish 3-form plural — keyed by count.
        'unpaid_invoices_desc' => '{0} brak nieuregulowanych|{1} :count faktura wystawiona|[2,4] :count faktury wystawione|[5,*] :count faktur wystawionych',

        'bookings_table_heading' => 'Dzisiejsze rezerwacje',
        'col_time' => 'Godzina',
        'col_horse' => 'Koń',
        'col_instructor' => 'Instruktor',
        'col_arena' => 'Manaż',
        'col_status' => 'Status',
        'empty_heading' => 'Brak rezerwacji na dziś',
        'empty_desc' => 'Spokojny dzień — albo czas na promocję!',
    ],

    'livejumping' => [
        'heading' => 'Nadchodzące starty (LiveJumping)',
        'description' => 'Konie i jeźdźcy ze stajni, którzy mają wpisane profile LJ.',
        'empty' => 'Brak nadchodzących startów. Dodaj URL profilu LiveJumping w karcie konia lub klienta.',
        'more_count' => '+ :count kolejnych',
    ],
];
