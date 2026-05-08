<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Typ',
            'performed_at' => 'Data zabiegu',
            'summary' => 'Krótki opis',
            'performed_by' => 'Wykonał',
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
