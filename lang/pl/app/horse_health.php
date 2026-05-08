<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Typ',
            'performed_at' => 'Data zabiegu',
            'summary' => 'Krótki opis',
            'performed_by' => 'Wykonał',
            'performed_by_placeholder' => 'np. asystent (jeśli inny niż specjalista)',
            'specialist' => 'Specjalista',
            'specialist_placeholder' => '— wybierz z listy —',
            'next_due_at' => 'Następny zabieg',
            'cost' => 'Koszt',
            'details' => 'Notatki',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Data',
            'type' => 'Typ',
            'summary' => 'Opis',
            'performed_by' => 'Wykonał',
            'next_due_at' => 'Następny',
        ],
    ],
];
