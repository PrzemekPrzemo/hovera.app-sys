<?php

declare(strict_types=1);

return [
    'navigation' => 'Obszar obsługi',
    'title' => 'Województwa obsługi',

    'section' => [
        'heading' => 'Wybierz województwa',
        'description' => 'Zaznacz te, w których prowadzisz transport. W trybie broadcast (anonimowe zapytania z formularza) otrzymasz leady z tych województw oraz z sąsiednich (adjacency map).',
    ],

    'form' => [
        'label' => [
            'voivodeships' => 'Województwa',
        ],
    ],

    'action' => [
        'save' => 'Zapisz wybór',
    ],

    'notify' => [
        'saved' => 'Obszar obsługi zaktualizowany',
        'saved_body' => 'Zaznaczono :direct województw, łączny zasięg z adjacency: :effective.',
    ],
];
