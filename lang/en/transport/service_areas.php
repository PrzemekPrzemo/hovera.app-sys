<?php

declare(strict_types=1);

return [
    'navigation' => 'Service area',
    'title' => 'Service voivodeships',

    'section' => [
        'heading' => 'Select voivodeships',
        'description' => 'Mark the ones you operate in. In broadcast mode (anonymous form inquiries) you will receive leads from these voivodeships and from adjacent ones (adjacency map).',
    ],

    'form' => [
        'label' => [
            'voivodeships' => 'Voivodeships',
        ],
    ],

    'action' => [
        'save' => 'Save selection',
    ],

    'notify' => [
        'saved' => 'Service area updated',
        'saved_body' => 'Selected :direct voivodeships, total coverage with adjacency: :effective.',
    ],
];
