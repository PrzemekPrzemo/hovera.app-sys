<?php

declare(strict_types=1);

return [
    'form' => [
        'label' => [
            'type' => 'Typ',
            'performed_at' => 'Kiedy',
            'performed_by' => 'Wykonał (imię stajennego)',
            'cost' => 'Dodatkowy koszt (opcjonalnie)',
            'summary' => 'Krótki opis',
            'summary_placeholder' => 'np. "Wypuszczenie 9:00-12:00, padok wschodni"',
            'details' => 'Notatki',
        ],
        'helper' => [
            'cost' => 'Wpisz tylko gdy aktywność naliczyła koszt poza ryczałtem (np. dodatkowe siano, transport).',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Data',
            'type' => 'Typ',
            'summary' => 'Opis',
            'performed_by' => 'Wykonał',
            'cost' => 'Koszt',
        ],
    ],
];
