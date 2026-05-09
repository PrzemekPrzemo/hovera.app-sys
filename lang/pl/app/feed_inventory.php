<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'item' => 'Pozycja paszowa',
        ],
        'label' => [
            'name' => 'Nazwa',
            'unit' => 'Jednostka',
            'low_stock_threshold' => 'Próg alertu',
            'sort_order' => 'Kolejność',
            'is_active' => 'Aktywne',
            'notes' => 'Notatki',
            'kind' => 'Typ ruchu',
            'amount' => 'Ilość (dodatnia)',
            'movement_date' => 'Data',
            'movement_notes' => 'Notatki ruchu',
        ],
        'helper' => [
            'low_stock_threshold' => 'Stan poniżej tej wartości pokaże alert. Pusto = bez alertu.',
            'amount' => 'Wpisz wartość dodatnią — kierunek wynika z typu ruchu.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Nazwa',
            'current_stock' => 'Stan',
            'low_stock_threshold' => 'Próg',
            'is_active' => 'Aktywne',
            'updated_at' => 'Ostatni ruch',
        ],
        'filter' => [
            'low_stock' => 'Z progiem alertu',
        ],
    ],
    'actions' => [
        'add_movement' => '+ Ruch magazynowy',
    ],
    'kind' => [
        'purchase' => 'Przyjęcie / dostawa',
        'consumption' => 'Wydanie / spożycie',
        'adjustment' => 'Korekta inwentaryzacyjna',
        'waste' => 'Odpis / utylizacja',
    ],
    'movements' => [
        'heading' => 'Historia ruchów',
        'col_date' => 'Data',
        'col_kind' => 'Typ',
        'col_amount' => 'Zmiana',
        'col_notes' => 'Notatki',
    ],
];
