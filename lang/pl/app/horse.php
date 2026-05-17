<?php

declare(strict_types=1);

return [
    'sex' => [
        'mare' => 'Klacz',
        'gelding' => 'Wałach',
        'stallion' => 'Ogier',
        'breeding_stallion' => 'Ogier kryjący',
    ],

    'form' => [
        'section' => [
            'identification' => 'Identyfikacja',
            'characteristics' => 'Charakterystyka',
            'boarding' => 'Pensja — usługi naliczane',
            'boarding_description' => 'Zaznacz które pozycje cennika dotyczą tego konia. Klient zobaczy je w portalu z miesięczną szacunkową kwotą.',
            'notes' => 'Notatki',
            'sport' => 'Sport (LiveJumping)',
            'sport_help' => 'Wklej URL profilu konia z LiveJumping.com — pokażemy palmares i nadchodzące starty.',
        ],
        'label' => [
            'name' => 'Imię',
            'owner' => 'Właściciel',
            'owner_placeholder' => '— stajnia —',
            'box' => 'Box',
            'box_placeholder' => '— bez przypisania —',
            'microchip' => 'Mikrochip',
            'passport_number' => 'Nr paszportu',
            'ueln' => 'UELN',
            'sex' => 'Płeć',
            'breed' => 'Rasa',
            'color' => 'Maść',
            'birth_date' => 'Data urodzenia',
            'boarding_services' => 'Usługi z cennika',
            'livejumping_profile_url' => 'URL profilu LiveJumping',
            'livejumping_palmares' => 'Palmares',
        ],
        'helper' => [
            'box' => 'Zmiana boxa zarejestruje historię w "Boxy → Historia przypisań".',
            'ueln' => 'Universal Equine Life Number',
            'boarding_services' => 'Cennik konfigurujesz w "Stajnia → Cennik pensji". Override ceny per koń (np. zniżka) ustawiasz tam ręcznie po utworzeniu wpisu.',
            'livejumping_profile_url' => 'Skopiuj adres strony profilu z livejumping.com — np. https://livejumping.com/horse/12345/romeo',
            'livejumping_no_profile' => 'Wklej URL profilu LJ powyżej, aby zobaczyć palmares.',
            'livejumping_fetch_failed' => 'Nie udało się pobrać danych z LiveJumping (sprawdź URL lub spróbuj później).',
        ],
        'stats' => [
            'starts' => 'Starty',
            'wins' => 'Zwycięstwa',
            'placings' => 'Miejsca w czołówce',
            'ranking_points' => 'Punkty rankingowe',
            'recent_results' => 'Ostatnie wyniki',
        ],
    ],

    'table' => [
        'column' => [
            'name' => 'Imię',
            'breed' => 'Rasa',
            'sex' => 'Płeć',
            'color' => 'Maść',
            'birth_date' => 'Ur.',
            'owner' => 'Właściciel',
            'owner_placeholder' => '— stajnia —',
            'created_at' => 'Dodany',
        ],
    ],
];
