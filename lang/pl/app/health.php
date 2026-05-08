<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'entry' => 'Wpis',
            'details' => 'Szczegóły',
        ],
        'label' => [
            'horse' => 'Koń',
            'type' => 'Typ',
            'performed_at' => 'Data zabiegu',
            'performed_by' => 'Wykonał (lekarz / kowal / firma)',
            'performed_by_placeholder' => 'np. asystent kowala (jeśli inna osoba niż wybrana wyżej)',
            'specialist' => 'Specjalista',
            'specialist_placeholder' => '— wybierz z listy specjalistów —',
            'summary' => 'Krótki opis',
            'summary_placeholder' => 'Szczepienie tężec + grypa',
            'next_due_at' => 'Następny zabieg',
            'cost' => 'Koszt',
            'details' => 'Notatki / leki / zalecenia',
        ],
        'helper' => [
            'next_due_at' => 'Dzięki temu pojawi się alert na dashboardzie.',
            'specialist' => 'Lista filtrowana wg typu wpisu — kowale dla "Kowal", weterynarze dla pozostałych typów. Skonfiguruj listę w Stajnia → Specjaliści.',
        ],
    ],

    'table' => [
        'column' => [
            'performed_at' => 'Data',
            'horse' => 'Koń',
            'type' => 'Typ',
            'summary' => 'Opis',
            'performed_by' => 'Wykonał',
            'next_due_at' => 'Następny',
            'cost' => 'Koszt',
        ],
        'filter' => [
            'horse' => 'Koń',
            'overdue' => 'Przeterminowane (next due in past)',
            'due_30' => 'Następny w 30 dni',
        ],
    ],
];
