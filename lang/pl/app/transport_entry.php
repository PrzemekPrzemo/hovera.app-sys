<?php

declare(strict_types=1);

return [
    'navigation' => 'Transport koni',
    'title' => 'Transport koni',

    'hero' => [
        'title' => 'Zamów transport koni',
        'free_badge' => '✓ Bezpłatnie w ramach Twojego planu — Hovera łączy Cię z zweryfikowanymi przewoźnikami.',
        'subtitle' => 'Wybierz, jak chcesz dotrzeć do przewoźnika. Łączymy Cię z :count zweryfikowanymi firmami transportowymi w Polsce.',
    ],

    'cta' => [
        'broadcast' => [
            'title' => 'Wyślij zapytanie do wszystkich w regionie',
            'subtitle' => 'Najszybciej — odpowiedź zwykle w 24h. Otrzymasz oferty mailem od kilku firm naraz.',
            'action' => 'Wyślij zapytanie',
        ],
        'directory' => [
            'title' => 'Przeglądaj firmy',
            'subtitle' => 'Wybierz konkretnego przewoźnika z opinii, regionu i floty.',
            'action' => 'Otwórz katalog',
        ],
        'favorites' => [
            'title' => 'Ulubieni przewoźnicy',
            'subtitle' => 'Twoje :count ulubionych firm — pre-wypełniamy w zapytaniu direct.',
            'action' => 'Zarządzaj listą',
        ],
    ],

    'stats' => [
        'your_leads' => 'Twoje zlecenia transportu',
    ],

    'disclaimer' => 'Hovera = pośrednik marketplace. Umowa transportu zawierana jest bezpośrednio z wybranym przewoźnikiem. Szczegóły w <a href="/regulamin-marketplace" target="_blank" class="underline">regulaminie marketplace transportowego</a>.',

    'upgrade_required' => 'Moduł transportu dostępny od planu Start. Upgrade tutaj →',

    // Banner pre-fill (publiczny inquiry view gdy user przychodzi z /app)
    'inquiry_banner' => [
        'from_stable' => 'Zlecenie z poziomu stajni :name',
        'back_to_app' => 'wróć do panelu',
    ],

    // Promo widget na dashboardzie
    'promo_widget' => [
        'title' => 'Potrzebujesz transportu koni?',
        'subtitle' => 'Zamów z Hovera w 1 minutę.',
        'stats' => 'Łączymy Cię z :count zweryfikowanymi przewoźnikami.',
        'cta' => 'Zamów transport',
        'dismiss' => 'Nie pokazuj więcej',
    ],

    // Header action na karcie konia
    'horse_action' => [
        'label' => 'Zamów transport',
    ],
];
