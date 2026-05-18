<?php

declare(strict_types=1);

return [
    'navigation' => 'Ulubieni przewoźnicy',
    'title' => 'Ulubieni przewoźnicy',

    'intro' => [
        'title' => 'Ulubieni przewoźnicy',
        'body' => 'Oznacz do :limit przewoźników jako ulubionych (obecnie :current). Przy składaniu zapytania transportowego pre-wypełnimy listę direct — wybierzesz 1-3 do których faktycznie chcesz wysłać zlecenie.',
    ],

    'search_placeholder' => 'Szukaj po nazwie firmy, NIP, slug…',
    'empty' => 'Brak zweryfikowanych firm transportowych spełniających kryteria.',

    'action' => [
        'add' => 'Dodaj do ulubionych',
        'remove' => 'Usuń z ulubionych',
    ],

    'notify' => [
        'added' => 'Dodano do ulubionych',
        'removed' => 'Usunięto z ulubionych',
        'limit_reached' => 'Limit ulubionych osiągnięty',
        'limit_body' => 'Maksymalnie :limit ulubionych. Najpierw usuń kogoś z listy.',
        'error' => 'Błąd',
    ],
];
