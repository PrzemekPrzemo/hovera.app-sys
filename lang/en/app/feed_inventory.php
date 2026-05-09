<?php

declare(strict_types=1);

return [
    'form' => [
        'section' => [
            'item' => 'Feed item',
        ],
        'label' => [
            'name' => 'Name',
            'unit' => 'Unit',
            'low_stock_threshold' => 'Alert threshold',
            'sort_order' => 'Sort order',
            'is_active' => 'Active',
            'notes' => 'Notes',
            'kind' => 'Movement type',
            'amount' => 'Amount (positive)',
            'movement_date' => 'Date',
            'movement_notes' => 'Movement notes',
        ],
        'helper' => [
            'low_stock_threshold' => 'Stock below this value triggers an alert. Empty = no alert.',
            'amount' => 'Enter a positive value — direction comes from the movement type.',
        ],
    ],
    'table' => [
        'column' => [
            'name' => 'Name',
            'current_stock' => 'Stock',
            'low_stock_threshold' => 'Threshold',
            'is_active' => 'Active',
            'updated_at' => 'Last movement',
        ],
        'filter' => [
            'low_stock' => 'With alert threshold',
        ],
    ],
    'actions' => [
        'add_movement' => '+ Stock movement',
    ],
    'kind' => [
        'purchase' => 'Purchase / delivery',
        'consumption' => 'Consumption',
        'adjustment' => 'Inventory adjustment',
        'waste' => 'Waste / discard',
    ],
    'movements' => [
        'heading' => 'Movement history',
        'col_date' => 'Date',
        'col_kind' => 'Type',
        'col_amount' => 'Change',
        'col_notes' => 'Notes',
    ],
];
