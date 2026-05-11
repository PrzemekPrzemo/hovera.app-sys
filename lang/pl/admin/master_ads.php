<?php

declare(strict_types=1);

return [
    'navigation' => 'Reklamy / komunikaty',

    'model' => [
        'label' => 'Reklama',
        'plural' => 'Reklamy / komunikaty',
    ],

    'section' => [
        'content' => 'Treść',
        'schedule' => 'Aktywność i harmonogram',
        'targeting' => 'Targetowanie',
        'targeting_help' => 'Puste pole = brak filtra (wszyscy pasują). Niepuste = ograniczenie. Wybór konkretnych użytkowników (na dole) nadpisuje pozostałe pola — komunikat trafi WYŁĄCZNIE do tych userów.',
    ],

    'field' => [
        'title' => 'Tytuł (bold w bannerze)',
        'body' => 'Treść komunikatu',
        'cta_label' => 'Etykieta przycisku CTA (opcjonalnie)',
        'cta_url' => 'URL po kliknięciu CTA (opcjonalnie)',
        'placement' => 'Umiejscowienie',
        'variant' => 'Wariant wizualny',
        'is_active' => 'Aktywna',
        'is_active_short' => 'Akt.',
        'starts_at' => 'Pokaż od',
        'starts_at_help' => 'Puste = od razu',
        'ends_at' => 'Pokaż do',
        'ends_at_help' => 'Puste = bezterminowo',
        'targeting_roles' => 'Role w stajni',
        'targeting_roles_help' => 'Pokażemy tylko userom z wybraną rolą w ich stajni.',
        'targeting_tenants' => 'Konkretne stajnie',
        'targeting_tenants_help' => 'Pokażemy tylko userom należącym do wybranych stajni.',
        'targeting_countries' => 'Kraje (kody ISO)',
        'targeting_countries_help' => 'Filtr po tenant.country (np. PL, DE, FR). Wpisuj kody dwuliterowe.',
        'targeting_locales' => 'Język UI użytkownika',
        'targeting_locales_help' => 'Filtr po user.locale (preferencja językowa konkretnej osoby).',
        'targeting_users' => 'Konkretni użytkownicy (override)',
        'targeting_users_help' => 'Wybór jednej lub kilku osób — nadpisuje pozostałe filtry. Reklama trafi WYŁĄCZNIE do nich.',
        'impressions' => 'Wyświetlenia',
        'clicks' => 'Kliknięcia',
    ],

    'placement' => [
        'banner' => 'Banner (na górze panelu)',
        'modal' => 'Modal (pop-up)',
    ],

    'variant' => [
        'info' => 'Informacja (ochra)',
        'promo' => 'Promocja (zielony)',
        'warning' => 'Ostrzeżenie (pomarańczowy)',
    ],

    'role' => [
        'instructor' => 'Trener / Instruktor',
        'employee' => 'Pracownik',
        'vet' => 'Weterynarz',
        'viewer' => 'Viewer (obserwator)',
    ],
];
