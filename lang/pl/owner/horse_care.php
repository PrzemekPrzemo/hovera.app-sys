<?php

declare(strict_types=1);

return [
    'page' => [
        'title' => 'Waga i żywienie',
        'breadcrumb' => 'Waga i żywienie',
    ],

    'header' => [
        'label' => 'Stajnia',
    ],

    'access' => [
        'denied' => 'Nie masz dostępu do tych danych. Twój koń musi być aktywnie zakwaterowany w stajni (lub mieć historyczny boarding) abyś widział wagę i plan żywienia.',
    ],

    'weight' => [
        'heading' => 'Pomiary masy ciała',
        'latest_prefix' => 'Ostatni:',
        'empty' => 'Stajnia jeszcze nie zalogowała żadnych pomiarów masy. Pomiary pojawią się tu gdy stajnia je zarejestruje (zwykle raz w miesiącu).',
        'col' => [
            'measured_at' => 'Data',
            'weight' => 'Waga',
            'delta' => 'Zmiana',
            'girth' => 'Obwód klatki',
            'notes' => 'Notatki',
        ],
    ],

    'feeding' => [
        'heading' => 'Plan żywienia',
        'note' => 'Plan ustalony przez stajnię — czytaj, ale nie edytuj.',
        'empty' => 'Stajnia nie ma jeszcze ustalonego planu żywienia dla tego konia.',
        'col' => [
            'meal' => 'Posiłek',
            'feed_type' => 'Pasza',
            'amount' => 'Ilość',
            'notes' => 'Notatki',
        ],
    ],

    'meal' => [
        'breakfast' => 'Śniadanie',
        'midday' => 'Południe',
        'evening' => 'Wieczór',
        'night' => 'Noc',
    ],
];
