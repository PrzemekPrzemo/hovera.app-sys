<?php

declare(strict_types=1);

return [
    'types' => [
        'indoor' => 'Indoor hall',
        'outdoor' => 'Outdoor arena',
        'paddock' => 'Paddock',
        'lunge' => 'Lunge ring',
        'field' => 'Field',
    ],

    'form' => [
        'label' => [
            'name' => 'Name',
            'type' => 'Type',
            'color' => 'Calendar color',
            'is_active' => 'Active',
            'sort_order' => 'Order',
            'notes' => 'Notes',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Name',
            'type' => 'Type',
            'color' => 'Color',
            'is_active' => 'Active',
        ],
    ],
];
