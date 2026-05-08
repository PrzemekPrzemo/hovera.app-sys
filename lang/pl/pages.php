<?php

declare(strict_types=1);

return [
    'profile' => [
        'navigation' => 'Profil',
        'title' => 'Twój profil',
    ],

    'calendar' => [
        'navigation' => 'Plan dnia',
    ],

    'tenant_settings' => [
        'navigation' => 'Ustawienia stajni',
        'title' => 'Ustawienia stajni',
    ],

    'invoicing_settings' => [
        'navigation' => 'Faktury i rozliczenia',
        'title' => 'Faktury i rozliczenia',
    ],

    'payment_settings' => [
        'navigation' => 'Płatności online',
        'title' => 'Płatności online',
    ],

    'ksef_settings' => [
        'navigation' => 'KSeF (e-faktury)',
        'title' => 'KSeF — krajowy system e-faktur',
    ],

    'company_lookup' => [
        'navigation' => 'GUS / KRS',
        'title' => 'Weryfikacja firm — GUS / KRS',
    ],

    'my_tasks' => [
        'navigation' => 'Moje zadania',
        'title' => 'Moje zadania',
        'signed_in_as' => 'Zalogowany jako specjalista',
        'sections' => [
            'overdue' => 'Przeterminowane',
            'upcoming' => 'Najbliższe zabiegi (30 dni)',
            'recent' => 'Ostatnio wykonane (30 dni)',
        ],
        'empty' => [
            'overdue' => 'Brak zaległych zadań — gratulacje!',
            'upcoming' => 'Brak zaplanowanych zabiegów w najbliższych 30 dniach.',
            'recent' => 'Brak wpisów z ostatnich 30 dni.',
        ],
        'overdue_by_days' => '{1} przeterminowane o 1 dzień|[2,4] przeterminowane o :days dni|[5,*] przeterminowane o :days dni',
        'in_days' => '{0} dziś|{1} jutro|[2,*] za :days dni',
    ],
];
