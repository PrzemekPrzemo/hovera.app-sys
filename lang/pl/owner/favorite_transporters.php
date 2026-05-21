<?php

declare(strict_types=1);

return [
    'navigation' => 'Ulubieni przewoźnicy',
    'navigation_group' => 'Transport',

    'model' => [
        'singular' => 'Ulubiony przewoźnik',
        'plural' => 'Ulubieni przewoźnicy',
    ],

    'form' => [
        'transporter' => 'Przewoźnik',
        'transporter_helper' => 'Lista zweryfikowanych przewoźników w sieci Hovera. Po dodaniu możesz wysłać im targeted zapytanie z „Zamów transport".',
        'notes' => 'Notatki (opcjonalne)',
        'notes_placeholder' => 'np. „odbiera Pegaza co czwartek na trening" lub „dobre opinie od stajni X"',
    ],

    'table' => [
        'name' => 'Przewoźnik',
        'slug' => 'Slug',
        'notes' => 'Notatki',
        'added' => 'Dodany',
    ],

    'empty' => [
        'heading' => 'Brak ulubionych przewoźników',
        'description' => 'Dodaj zaufanych przewoźników — przy „Zamów transport" możesz wybrać „wyślij tylko do moich ulubionych" zamiast broadcastu do wszystkich.',
    ],

    'action' => [
        'add' => 'Dodaj przewoźnika',
    ],
];
