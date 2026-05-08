<?php

declare(strict_types=1);

return [
    'types' => [
        'vet' => 'Weterynarz',
        'farrier' => 'Kowal',
    ],
    'types_short' => [
        'vet' => 'Wet.',
        'farrier' => 'Kowal',
    ],

    'form' => [
        'section' => [
            'data' => 'Dane specjalisty',
            'access' => 'Konto w systemie (opcjonalne)',
            'access_description' => 'Powiąż z kontem pracownika stajni — tylko jeśli specjalista loguje się do panelu i ma być widoczny widok "Moje zadania". Większość kowali / weterynarzy to kontrahenci zewnętrzni — zostaw puste.',
        ],
        'label' => [
            'type' => 'Specjalność',
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
            'color' => 'Kolor w kalendarzu',
            'central_user' => 'Powiąż z pracownikiem',
            'central_user_placeholder' => '— bez konta —',
            'is_active' => 'Aktywny',
            'sort_order' => 'Kolejność',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'central_user' => 'Lista zawiera tylko aktywnych członków stajni. Wybór tutaj umożliwia później widok "Moje zadania" dla zalogowanego specjalisty.',
        ],
    ],

    'table' => [
        'column' => [
            'type' => 'Specjalność',
            'name' => 'Imię i nazwisko',
            'phone' => 'Telefon',
            'central_user' => 'Konto',
            'is_active' => 'Aktywny',
        ],
        'filter' => [
            'type' => 'Specjalność',
            'has_account' => 'Z kontem w systemie',
        ],
        'has_account_yes' => 'Tak',
        'has_account_no' => '—',
    ],
];
