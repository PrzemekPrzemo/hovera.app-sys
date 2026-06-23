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
            'external_link' => 'Konto specjalisty w Hoverze',
            'is_active' => 'Aktywny',
            'sort_order' => 'Kolejność',
            'notes' => 'Notatki',
        ],
        'helper' => [
            'central_user' => 'Lista zawiera tylko aktywnych członków stajni. Wybór tutaj umożliwia później widok "Moje zadania" dla zalogowanego specjalisty.',
        ],
        'external_link' => [
            'empty' => 'Podaj e-mail, aby sprawdzić powiązanie z kontem Hovera.',
            'none' => 'Kontakt zewnętrzny (brak konta w Hoverze)',
            'verified' => 'Zweryfikowany specjalista Hovera',
            'unverified' => 'Specjalista Hovera (niezweryfikowany)',
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

    'specialty' => [
        'vet' => 'Weterynarz',
        'farrier' => 'Kowal',
        'groomer' => 'Groomer',
        'dietetyk' => 'Dietetyk',
        'other' => 'Inny',
    ],

    'action' => [
        'create' => [
            'label' => 'Dodaj kontakt lokalny',
        ],
        'invite' => [
            'label' => 'Zaproś weterynarza',
            'email' => 'E-mail weterynarza',
            'display_name' => 'Imię i nazwisko',
            'display_name_placeholder' => 'dr Anna Kowalska',
            'specialty' => 'Specjalność',
            'modal_heading' => 'Zaproś specjalistę do Hovery',
            'modal_description' => 'System wyśle 7-dniowy link aktywacyjny na podany e-mail. Po ustawieniu hasła specjalista dostanie dostęp do swojego panelu w Hoverze. Konto wymaga weryfikacji przez zespół Hovery zanim pojawi się oznaczenie „zweryfikowane".',
            'submit' => 'Wyślij zaproszenie',
            'no_tenant' => 'Brak kontekstu stajni — odśwież stronę i spróbuj ponownie.',
            'notify' => [
                'created_title' => 'Zaproszenie wysłane',
                'created_body' => 'Specjalista (:email) otrzyma e-mail z linkiem aktywacyjnym (7 dni).',
                'reissued_title' => 'Nowy link aktywacyjny wysłany',
                'reissued_body' => 'Specjalista (:email) miał już konto bez ustawionego hasła — wysłaliśmy świeży link.',
                'already_setup_title' => 'Specjalista ma już aktywne konto',
                'already_setup_body' => ':email jest już zarejestrowany — nie wysyłamy ponownie zaproszenia. Aby dodać go do swojej stajni, użyj dedykowanej akcji (w przyszłości).',
            ],
        ],
    ],
];
